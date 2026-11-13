<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\AssignUserBranch;
use App\Actions\Users\CreateStaffUser;
use App\Actions\Users\DeactivateUser;
use App\Actions\Users\RestoreUser;
use App\Actions\Users\SyncUserRoles;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use App\Support\Rbac\BranchScope;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Users (EUISIS RBAC): administrators open staff accounts, change their
 * details, give them one or more roles, say where their branch-scoped roles
 * apply, and deactivate or restore them. Applicants register themselves and
 * are listed for reference only.
 */
class UserController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $roles = Role::query()->orderBy('name')->get();
        $role = is_string($request->query('role')) ? $request->query('role') : '';
        $role = $role === 'none' || $roles->contains('name', $role) ? $role : '';
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : '';

        $users = User::query()
            // Each row says whether the person is staff, by their permissions: load them with the page.
            ->with(['roles:id,name,name_am,scope_type', 'roles.permissions:id,name', 'permissions:id,name', 'branch'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($role === 'none', fn (Builder $query) => $query->whereDoesntHave('roles'))
            ->when($role !== '' && $role !== 'none', fn (Builder $query) => $query->role($role))
            ->when($status === 'active', fn (Builder $query) => $query->whereNull('deactivated_at'))
            ->when($status === 'inactive', fn (Builder $query) => $query->whereNotNull('deactivated_at'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => $this->presentRow($user));

        return Inertia::render('admin/users/index', [
            'users' => Paginated::present($users),
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
            'roleOptions' => $roles->map(fn (Role $item): array => ['value' => $item->name, 'label' => $item->label()])->values()->all(),
            'can' => ['create' => $request->user()?->can('create', User::class) ?? false],
            'urls' => ['index' => route('admin.users.index'), 'create' => route('admin.users.create')],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/create', [
            'roles' => $this->pickableRoles($request->user()),
            'branches' => $this->branchOptions(),
            'urls' => ['index' => route('admin.users.index'), 'store' => route('admin.users.store')],
        ]);
    }

    public function store(StoreStaffUserRequest $request, CreateStaffUser $createStaffUser): RedirectResponse
    {
        $user = $createStaffUser->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->roles(),
            $request->user(),
            branch: $request->branch(),
        );

        return to_route('admin.users.edit', $user)->with('success', $user->must_change_password
            ? __('admin.users.messages.created_default_password')
            : __('admin.users.messages.created'));
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);

        $viewer = $request->user();
        $user->load(['roles:id,name,name_am,scope_type', 'branch', 'branchScopes.branch', 'deactivatedBy:id,name']);
        $can = [
            'update' => Gate::inspect('update', $user)->allowed(),
            'assignRoles' => Gate::inspect('assignRoles', $user)->allowed(),
            'deactivate' => Gate::inspect('deactivate', $user)->allowed(),
            'restore' => $viewer?->can('restore', $user) ?? false,
        ];
        $rolesLocked = Gate::inspect('assignRoles', $user);
        $scope = $user->branchScope();

        return Inertia::render('admin/users/edit', [
            'user' => [
                ...$this->presentRow($user),
                'branch_id' => $user->branch_id,
                'role_names' => $user->roles->pluck('name')->values()->all(),
                'is_applicant' => $user->isApplicant(),
                'deactivated_at' => EthiopianCalendar::present($user->deactivated_at?->setTimezone((string) config('localization.display_timezone')), app()->getLocale()),
                'deactivated_by' => $user->deactivatedBy?->name,
                'sees_everywhere' => $scope->everywhere,
            ],
            'roles' => $this->pickableRoles($viewer, $user),
            'branches' => $this->branchOptions($user->branch),
            'scopes' => $user->branchScopes
                ->sortBy(fn (UserBranchScope $row): string => $row->is_head_office ? '' : (string) $row->branch?->code)
                ->map(fn (UserBranchScope $row): array => [
                    'id' => $row->id,
                    'key' => $row->key(),
                    'name' => $row->is_head_office ? __('rbac.scopes.headOffice') : (string) $row->branch?->localizedName(app()->getLocale()),
                    'code' => $row->is_head_office ? null : $row->branch?->code,
                    'active' => $row->is_head_office || (bool) $row->branch?->is_active,
                    'destroyUrl' => route('admin.users.branch-scopes.destroy', [$user, $row]),
                ])->values()->all(),
            'scopeBranches' => $this->scopeBranchOptions(),
            'can' => $can,
            'rolesLockedReason' => $rolesLocked->allowed() ? null : $rolesLocked->message(),
            'urls' => [
                'index' => route('admin.users.index'),
                'update' => route('admin.users.update', $user),
                'roles' => route('admin.users.roles', $user),
                'deactivate' => route('admin.users.deactivate', $user),
                'restore' => route('admin.users.restore', $user),
                'scopes' => route('admin.users.branch-scopes.store', $user),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AssignUserBranch $assignUserBranch, AuditLog $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $assignUserBranch, $audit): void {
            $name = trim($request->string('name')->toString());

            if ($name !== $user->name) {
                $old = $user->name;
                $user->forceFill(['name' => $name])->save();
                $audit->record(AuditEvent::UserUpdated, $user, $request->user(), old: ['name' => $old], new: ['name' => $name]);
            }

            $assignUserBranch->handle($user, $request->branch(), $request->user());
        });

        return back()->with('success', __('rbac.users.messages.updated'));
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user, SyncUserRoles $syncUserRoles): RedirectResponse
    {
        $syncUserRoles->handle($user, $request->roles(), $request->user());

        return back()->with('success', __('admin.users.messages.role_changed'));
    }

    public function deactivate(Request $request, User $user, DeactivateUser $deactivateUser): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $deactivateUser->handle($user, $request->user());

        return back()->with('success', __('rbac.users.messages.deactivated'));
    }

    public function restore(Request $request, User $user, RestoreUser $restoreUser): RedirectResponse
    {
        Gate::authorize('restore', $user);

        $restoreUser->handle($user, $request->user());

        return back()->with('success', __('rbac.users.messages.restored'));
    }

    /**
     * The roles the viewer may give: every role but the applicant's; the
     * administrator role and global roles only for administrators, though a
     * person keeps one they already hold.
     *
     * @return list<array{id: int, name: string, label: string, scope: string}>
     */
    private function pickableRoles(?User $viewer, ?User $target = null): array
    {
        $wide = $viewer?->can('manageGlobal', Role::class) ?? false;
        $held = $target?->roles->pluck('name')->all() ?? [];

        return Role::query()
            ->where('name', '!=', RoleEnum::Applicant->value)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role): bool => $wide
                || in_array($role->name, $held, true)
                || (! $role->isProtected() && ! $role->isGlobal()))
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label(),
                'scope' => $role->isProtected() ? Role::SCOPE_GLOBAL : $role->scope_type,
            ])
            ->values()
            ->all();
    }

    /**
     * Where staff can work: the open branches, and the person's own even when it has since closed.
     *
     * @return list<array{id: int, code: string, name: string, is_active: bool}>
     */
    private function branchOptions(?Branch $current = null): array
    {
        return Branch::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($current, fn (Builder $query) => $query->orWhere('id', $current->id)))
            ->orderBy('code')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->localizedName(app()->getLocale()),
                'is_active' => $branch->is_active,
            ])
            ->all();
    }

    /**
     * What a scope row may name: the head office and the open branches.
     *
     * @return list<array{key: string, code: string|null, name: string}>
     */
    private function scopeBranchOptions(): array
    {
        return [
            ['key' => BranchScope::HEAD_OFFICE_KEY, 'code' => null, 'name' => __('rbac.scopes.headOffice')],
            ...Branch::query()->where('is_active', true)->orderBy('code')->get()->map(fn (Branch $branch): array => [
                'key' => (string) $branch->id,
                'code' => $branch->code,
                'name' => $branch->localizedName(app()->getLocale()),
            ])->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, roles: list<array{name: string, label: string}>, branch: array{code: string, name: string}|null, is_staff: bool, active: bool, two_factor: bool, created_at: array<string, mixed>|null, editUrl: string}
     */
    private function presentRow(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->map(fn (Model $role): array => [
                'name' => (string) $role->getAttribute('name'),
                'label' => $role instanceof Role ? $role->label() : (string) $role->getAttribute('name'),
            ])->values()->all(),
            'branch' => $user->branch === null ? null : ['code' => $user->branch->code, 'name' => $user->branch->localizedName(app()->getLocale())],
            'is_staff' => $user->isStaff(),
            'is_admin' => $user->roles->contains('name', PermissionCatalog::PROTECTED_ROLE),
            'active' => $user->isActive(),
            'two_factor' => $user->two_factor_confirmed_at !== null,
            'created_at' => EthiopianCalendar::present($user->created_at?->setTimezone((string) config('localization.display_timezone')), app()->getLocale()),
            'editUrl' => route('admin.users.edit', $user),
        ];
    }
}
