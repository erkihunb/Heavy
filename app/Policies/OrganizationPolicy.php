<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\User;

/**
 * The organizations requests are registered for: seen, corrected and deleted,
 * each by its own permission. The Authority's own organization is not one of
 * them (its vehicles have their own register).
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewOrganizations->value);
    }

    public function view(User $user, Organization $organization): bool
    {
        return ! $organization->is_institution && $user->can(Permission::ViewOrganizations->value);
    }

    public function update(User $user, Organization $organization): bool
    {
        return ! $organization->is_institution && $user->can(Permission::UpdateOrganizations->value);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return ! $organization->is_institution && $user->can(Permission::DeleteOrganizations->value);
    }

    public function updateVehicle(User $user, Organization $organization): bool
    {
        return ! $organization->is_institution && $user->can(Permission::UpdateVehicles->value);
    }

    public function deleteVehicle(User $user, Organization $organization): bool
    {
        return ! $organization->is_institution && $user->can(Permission::DeleteVehicles->value);
    }
}
