<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\Response;

class VehiclePolicy
{
    /**
     * Reaching the vehicle register. Which vehicles are listed is always
     * limited to the user's own organization by the query.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageOwnVehicles->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOwnVehicles->value)
            && $user->organization_id !== null;
    }

    public function view(User $user, Vehicle $vehicle): Response
    {
        return $this->ownedByOrganizationOf($user, $vehicle);
    }

    public function update(User $user, Vehicle $vehicle): Response
    {
        return $this->ownedByOrganizationOf($user, $vehicle);
    }

    /**
     * A vehicle of another organization is reported as "not found", so its
     * existence is not revealed.
     */
    private function ownedByOrganizationOf(User $user, Vehicle $vehicle): Response
    {
        return $user->can(Permission::ManageOwnVehicles->value)
            && $user->organization_id !== null
            && $user->organization_id === $vehicle->organization_id
                ? Response::allow()
                : Response::denyAsNotFound();
    }
}
