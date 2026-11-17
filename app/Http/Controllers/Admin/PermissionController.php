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
 * it, so new ones are added in code (see the README). The list is a matrix:
 * a row per permission, a column per role, a tick where the role holds it.
 */
class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('permissions.view');

        $search = mb_strtolower(trim((string) $request->query('search', '')));
        // The matrix's columns: default roles first, then the created ones by name.
        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        // One list, in catalogue order: permissions are not put into categories.
        $rows = collect(PermissionCatalog::names())
            ->map(function (string $name) use ($roles): array {
                $holders = $roles
                    ->filter(fn (Role $role): bool => $role->permissions->contains('name', $name))
                    ->pluck('name')
                    ->values()
                    ->all();

                return [
                    ...PermissionCatalog::entry($name),
                    // The names of the roles that hold it: a tick in that role's column.
                    'roles' => $holders,
                    'roles_count' => count($holders),
                    'showUrl' => route('admin.permissions.show', $name),
                ];
            });

        $visible = $rows
            ->filter(fn (array $row): bool => $search === ''
                || str_contains(mb_strtolower($row['name'].' '.$row['label'].' '.$row['description']), $search))
            ->values();

        return Inertia::render('admin/permissions/index', [
            'permissions' => $visible->all(),
            'roles' => $roles
                ->map(fn (Role $role): array => ['name' => $role->name, 'label' => $role->label()])
                ->all(),
            'stats' => [
                'total' => $rows->count(),
                'unassigned' => $rows->where('roles_count', 0)->count(),
            ],
            'filters' => ['search' => $search],
            'urls' => ['index' => route('admin.permissions.index')],
        ]);
    }

    public function show(Request $request, string $permission): Response
    {
        Gate::authorize('permissions.view');
        abort_unless(in_array($permission, PermissionCatalog::names(), true), 404);

        /** @var User $actor */
        $actor = $request->user();

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
            'permission' => PermissionCatalog::entry($permission),
            'roles' => $roles,
            'urls' => ['index' => route('admin.permissions.index')],
        ]);
    }
}
