<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\VehicleType;

class VehicleTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageVehicleTypes->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageVehicleTypes->value);
    }

    public function update(User $user, VehicleType $vehicleType): bool
    {
        return $user->can(Permission::ManageVehicleTypes->value);
    }
}
