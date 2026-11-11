<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * An applicant may create one organization, and only while they have none.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOwnOrganization->value)
            && $user->organization_id === null;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can(Permission::ManageOwnOrganization->value)
            && $user->organization_id === $organization->id;
    }

    /**
     * Only the member who created the profile (its owner) may change it.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $organization->created_by === $user->id;
    }
}
