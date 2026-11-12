<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The permission catalogue, read-only (EUISIS PermissionController without
 * create, edit or delete): in Heavy a permission exists because code checks
 * it, so new ones are added in code (see the README).
 */
class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('permissions.view');

        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $module = array_key_exists((string) $request->query('module'), PermissionCatalog::MODULES) ? (string) $request->query('module') : '';
        $holders = $this->roleCounts();

        $rows = collect(PermissionCatalog::names())
            ->map(fn (string $name): array => [
                ...PermissionCatalog::entry($name),
                'group' => (string) PermissionCatalog::groupOf($name),
                'module' => (string) PermissionCatalog::moduleOf($name),
                'roles_count' => $holders[$name] ?? 0,
                'showUrl' => route('admin.permissions.show', $name),
            ]);

        $visible = $rows
            ->filter(fn (array $row): bool => ($module === '' || $row['module'] === $module)
                && ($search === '' || str_contains(mb_strtolower($row['name'].' '.$row['label'].' '.$row['description']), $search)))
            ->values();

        return Inertia::render('admin/permissions/index', [
            'permissions' => $visible->all(),
            'modules' => PermissionCatalog::modules(),
            'stats' => [
                'total' => $rows->count(),
                'modules' => count(PermissionCatalog::MODULES),
                'unassigned' => $rows->where('roles_count', 0)->count(),
            ],
            'filters' => ['search' => $search, 'module' => $module],
            'urls' => ['index' => route('admin.permissions.index')],
        ]);
    }

    public function show(Request $request, string $permission): Response
    {
        Gate::authorize('permissions.view');
        abort_unless(in_array($permission, PermissionCatalog::names(), true), 404);

        /** @var User $actor */
        $actor = $request->user();
        $module = (string) PermissionCatalog::moduleOf($permission);

        $roles = Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label(),
                'scope_type' => $role->scope_type,
                'users_count' => (int) $role->getAttribute('users_count'),
                'editUrl' => $actor->can('update', $role) ? route('admin.roles.edit', $role) : null,
            ])
            ->all();

        return Inertia::render('admin/permissions/show', [
            'permission' => [
                ...PermissionCatalog::entry($permission),
                'group' => (string) PermissionCatalog::groupOf($permission),
                'module' => $module,
                'moduleName' => collect(PermissionCatalog::modules())->firstWhere('key', $module)['name'] ?? $module,
            ],
            'roles' => $roles,
            'urls' => ['index' => route('admin.permissions.index')],
        ]);
    }

    /**
     * permission name => how many roles hold it.
     *
     * @return array<string, int>
     */
    private function roleCounts(): array
    {
        return Role::query()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->countBy()
            ->all();
    }
}
