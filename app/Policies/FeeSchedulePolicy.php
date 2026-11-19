<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FeeSchedule;
use App\Models\User;

/**
 * FeeSchedules: seen, added, changed and deleted, each by its own permission.
 */
class FeeSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewFeeSchedules->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateFeeSchedules->value);
    }

    public function update(User $user, FeeSchedule $feeSchedule): bool
    {
        return $user->can(Permission::UpdateFeeSchedules->value);
    }

    public function delete(User $user, FeeSchedule $feeSchedule): bool
    {
        return $user->can(Permission::DeleteFeeSchedules->value);
    }
}
