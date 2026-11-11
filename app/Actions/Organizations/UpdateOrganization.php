<?php

namespace App\Actions\Organizations;

use App\Data\OrganizationData;
use App\Models\Organization;

class UpdateOrganization
{
    public function handle(Organization $organization, OrganizationData $data): Organization
    {
        $organization->update($data->toAttributes());

        return $organization;
    }
}
