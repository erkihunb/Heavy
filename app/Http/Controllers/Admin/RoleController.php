<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Roles screens (EUISIS RoleController): list, create, change and delete
 * roles and choose their permissions from the catalogue. Every change is
 * audited and clears the permission cache.
 */
class RoleController extends Controller
{
    private const int PER_PAGE = 15;

    public function __construct(
        private readonly AuditLog $audit,
        private readonly PermissionRegistrar $registrar,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'scope' => in_array($request->query('scope'), [Role::SCOPE_BRANCH, Role::SCOPE_GLOBAL], true) ? (string) $request->query('scope') : '',
            'module' => array_key_exists((string) $request->query('module'), PermissionCatalog::MODULES) ? (string) $request->query('module') : '',
        ];

        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereLike('name', "%{$filters['search']}%")->orWhereLike('name_am', "%{$filters['search']}%"),
            ))
            ->when($filters['scope'] !== '', fn (Builder $query) => $query->where('scope_type', $filters['scope']))
            ->when($filters['module'] !== '', fn (Builder $query) => $query->whereHas(
                'permissions',
                fn (Builder $permissions) => $permissions->whereIn('name', $this->moduleNames($filters['module'])),
            ))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $actor = $request->user();

        return Inertia::render('admin/roles/index', [
            'roles' => [
                'data' => $roles->getCollection()->map(fn (Role $role): array => $this->presentRow($role, $actor))->all(),
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
                'from' => $roles->firstItem(),
                'to' => $roles->lastItem(),
            ],
            'filters' => $filters,
            'modules' => PermissionCatalog::modules(),
            'scopeCounts' => [
                Role::SCOPE_BRANCH => Role::query()->where('scope_type', Role::SCOPE_BRANCH)->count(),
                Role::SCOPE_GLOBAL => Role::query()->where('scope_type', Role::SCOPE_GLOBAL)->count(),
            ],
            'can' => ['create' => $actor?->can('create', Role::class) ?? false],
            'urls' => ['index' => route('admin.roles.index'), 'create' => route('admin.roles.create')],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/roles/create', [
            'permissions' => PermissionCatalog::grouped(),
            'modules' => PermissionCatalog::modules(),
            'can' => [
                'assignPermissions' => true,
                'manageGlobalRoles' => $request->user()?->can('manageGlobal', Role::class) ?? false,
            ],
            'urls' => ['index' => route('admin.roles.index'), 'store' => route('admin.roles.store')],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $role = DB::transaction(function () use ($request): Role {
            $role = new Role;
            $role->forceFill([
                'name' => $request->validated('name'),
                'guard_name' => 'web',
                'name_am' => $request->validated('name_am'),
                'scope_type' => $request->validated('scope_type'),
                'is_system' => false,
            ])->save();
            $role->syncPermissions($request->permissionNames());

            $this->audit->record(AuditEvent::RoleCreated, $role, $request->user(), new: [
                'name' => $role->name,
                'scope_type' => $role->scope_type,
                'permissions' => $request->permissionNames(),
            ]);

            return $role;
        });

        $this->registrar->forgetCachedPermissions();

        return to_route('admin.roles.edit', $role)->with('success', __('rbac.roles.messages.created', ['name' => $role->label()]));
    }

    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize('update', $role);

        $actor = $request->user();

        return Inertia::render('admin/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label(),
                'name_am' => $role->name_am,
                'scope_type' => $role->scope_type,
                'permissions' => $role->permissions()->pluck('name')->values()->all(),
                'is_protected' => $role->isProtected(),
                'is_system' => $role->is_system,
                'users_count' => $role->users()->count(),
            ],
            'permissions' => PermissionCatalog::grouped(),
            'modules' => PermissionCatalog::modules(),
            'can' => [
                'assignPermissions' => $actor?->can('assignPermissions', $role) ?? false,
                // A global role stays global for whoever may edit it; only administrators make one.
                'manageGlobalRoles' => $role->isGlobal() || ($actor?->can('manageGlobal', Role::class) ?? false),
            ],
            'urls' => ['index' => route('admin.roles.index'), 'update' => route('admin.roles.update', $role)],
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $oldPermissions = $role->permissions()->pluck('name')->sort()->values()->all();
        $newPermissions = collect($request->permissionNames())->sort()->values()->all();
        $permissionsChanging = $newPermissions !== $oldPermissions;

        // The administrator role keeps every staff permission whatever the form sent.
        if ($permissionsChanging && ! $request->user()?->can('assignPermissions', $role)) {
            $newPermissions = $oldPermissions;
            $permissionsChanging = false;
        }

        $old = ['name' => $role->name, 'name_am' => $role->name_am, 'scope_type' => $role->scope_type];

        DB::transaction(function () use ($request, $role, $newPermissions, $permissionsChanging, $oldPermissions, $old): void {
            $role->forceFill([
                'name' => $request->validated('name'),
                'name_am' => $request->validated('name_am'),
                'scope_type' => $request->validated('scope_type'),
            ])->save();

            if ($permissionsChanging) {
                $role->syncPermissions($newPermissions);
            }

            $this->audit->record(
                AuditEvent::RoleUpdated,
                $role,
                $request->user(),
                old: [...$old, 'permissions' => $oldPermissions],
                new: ['name' => $role->name, 'name_am' => $role->name_am, 'scope_type' => $role->scope_type, 'permissions' => $newPermissions],
            );
        });

        $this->registrar->forgetCachedPermissions();

        return to_route('admin.roles.index')->with('success', __('rbac.roles.messages.updated', ['name' => $role->label()]));
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $decision = Gate::inspect('delete', $role);

        // Refused for a reason the person can act on (still assigned, a default role): say so on the list.
        if ($decision->denied()) {
            abort_if($decision->message() === null, 403);

            return back()->with('warning', $decision->message());
        }

        $label = $role->label();

        DB::transaction(function () use ($request, $role): void {
            $this->audit->record(AuditEvent::RoleDeleted, $role, $request->user(), old: [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->all(),
            ]);
            $role->delete();
        });

        $this->registrar->forgetCachedPermissions();

        return to_route('admin.roles.index')->with('success', __('rbac.roles.messages.deleted', ['name' => $label]));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(Role $role, ?User $actor): array
    {
        $names = $role->permissions->pluck('name');
        $modules = $names
            ->map(fn (string $name): ?string => PermissionCatalog::moduleOf($name))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->all();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->label(),
            'name_am' => $role->name_am,
            'scope_type' => $role->scope_type,
            'users_count' => (int) $role->getAttribute('users_count'),
            'permissions_count' => $names->count(),
            'modules' => $modules,
            'is_protected' => $role->isProtected(),
            'is_system' => $role->is_system,
            'can' => [
                'update' => $actor?->can('update', $role) ?? false,
                // Shown for created roles; the server explains a refusal (still assigned).
                'delete' => ! $role->is_system && ! $role->isProtected() && ($actor?->can('roles.delete') ?? false),
            ],
            'editUrl' => route('admin.roles.edit', $role),
            'destroyUrl' => route('admin.roles.destroy', $role),
        ];
    }

    /**
     * @return list<string>
     */
    private function moduleNames(string $module): array
    {
        return array_merge(...array_values(PermissionCatalog::MODULES[$module]));
    }
}
