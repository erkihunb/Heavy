<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;

/**
 * Branchs: seen, added, changed and deleted, each by its own permission.
 */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewBranches->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateBranches->value);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can(Permission::UpdateBranches->value);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can(Permission::DeleteBranches->value);
    }
}
