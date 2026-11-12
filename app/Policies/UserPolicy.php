<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * User administration (EUISIS RBAC): who may list users, open staff
 * accounts, change their details, give roles and branch scopes, and
 * deactivate or restore them. Applicants register themselves and are listed
 * for reference only: their roles and scopes are not changed here.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewUsers->value);
    }

    public function view(User $user, User $target): bool
    {
        return $user->can(Permission::ViewUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateUsers->value);
    }

    /**
     * A member of staff's name, e-mail and the branch they work at.
     */
    public function update(User $user, User $target): Response
    {
        if (! $user->can(Permission::UpdateUsers->value)) {
            return Response::deny();
        }

        if ($target->hasRole(Role::Applicant->value)) {
            return Response::deny(__('admin.users.errors.applicant'));
        }

        return Response::allow();
    }

    /**
     * The roles a member of staff holds and where their branch-scoped roles
     * apply. Nobody changes their own: the system always keeps an
     * administrator and nobody locks themselves out.
     */
    public function assignRoles(User $user, User $target): Response
    {
        if (! $user->can(Permission::AssignRoles->value)) {
            return Response::deny();
        }

        if ($user->is($target)) {
            return Response::deny(__('admin.users.errors.own_role'));
        }

        if ($target->hasRole(Role::Applicant->value)) {
            return Response::deny(__('admin.users.errors.applicant'));
        }

        return Response::allow();
    }

    /**
     * Taking an account's access away. The last active administrator is
     * protected by App\Actions\Users\DeactivateUser.
     */
    public function deactivate(User $user, User $target): Response
    {
        if (! $user->can(Permission::DeactivateUsers->value)) {
            return Response::deny();
        }

        if ($user->is($target)) {
            return Response::deny(__('rbac.users.errors.deactivate_self'));
        }

        return Response::allow();
    }

    public function restore(User $user, User $target): bool
    {
        return $user->can(Permission::DeactivateUsers->value);
    }

    /**
     * Registering applicants who come to the office (Registrar, Admin).
     */
    public function registerApplicant(User $user): bool
    {
        return $user->can(Permission::RegisterApplicants->value);
    }
}
