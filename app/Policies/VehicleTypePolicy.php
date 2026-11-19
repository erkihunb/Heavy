<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\VehicleType;

/**
 * VehicleTypes: seen, added, changed and deleted, each by its own permission.
 */
class VehicleTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewVehicleTypes->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateVehicleTypes->value);
    }

    public function update(User $user, VehicleType $vehicleType): bool
    {
        return $user->can(Permission::UpdateVehicleTypes->value);
    }

    public function delete(User $user, VehicleType $vehicleType): bool
    {
        return $user->can(Permission::DeleteVehicleTypes->value);
    }
}
