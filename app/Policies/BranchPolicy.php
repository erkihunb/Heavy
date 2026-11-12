<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;

/**
 * Branches are part of how the system is set up: whoever manages the
 * settings manages them.
 */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }
}
