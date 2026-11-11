<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * User administration (spec §11): Admins create staff accounts and change
 * staff roles. Applicants register themselves and are not managed here.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    /**
     * An Admin never changes their own role, so the system always keeps at
     * least one Admin and nobody locks themselves out.
     */
    public function updateRole(User $user, User $target): Response
    {
        if (! $user->can(Permission::ManageUsers->value)) {
            return Response::deny();
        }

        if ($user->is($target)) {
            return Response::deny(__('admin.users.errors.own_role'));
        }

        if ($target->hasRole('applicant')) {
            return Response::deny(__('admin.users.errors.applicant'));
        }

        return Response::allow();
    }
}
