<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Auth\Access\Response;

/**
 * Who may see and change roles (EUISIS RolePolicy, on Heavy's permissions).
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    /**
     * The administrator role is changed only by an administrator.
     */
    public function update(User $user, Role $role): Response
    {
        if (! $user->can('roles.update')) {
            return Response::deny();
        }

        if ($role->isProtected() && ! $user->hasRole(PermissionCatalog::PROTECTED_ROLE)) {
            return Response::deny(__('rbac.roles.errors.protected'));
        }

        return Response::allow();
    }

    /**
     * The permissions of the administrator role are fixed: it always holds every staff permission.
     */
    public function assignPermissions(User $user, Role $role): bool
    {
        return ! $role->isProtected() && $user->can('roles.update');
    }

    /**
     * Global roles see every branch, so only an administrator makes or keeps one.
     */
    public function manageGlobal(User $user): bool
    {
        return $user->hasRole(PermissionCatalog::PROTECTED_ROLE);
    }

    /**
     * Created roles nobody holds can be deleted; the default roles and the
     * administrator role cannot.
     */
    public function delete(User $user, Role $role): Response
    {
        if (! $user->can('roles.delete')) {
            return Response::deny();
        }

        if ($role->isProtected() || $role->is_system) {
            return Response::deny(__('rbac.roles.errors.system'));
        }

        if ($role->users()->exists()) {
            return Response::deny(__('rbac.roles.errors.assigned'));
        }

        return Response::allow();
    }
}
