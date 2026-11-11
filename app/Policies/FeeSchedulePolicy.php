<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FeeSchedule;
use App\Models\User;

/**
 * The fee schedule is maintained by Admins only (spec §2).
 */
class FeeSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageFeeSchedules->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageFeeSchedules->value);
    }

    public function update(User $user, FeeSchedule $feeSchedule): bool
    {
        return $user->can(Permission::ManageFeeSchedules->value);
    }
}
