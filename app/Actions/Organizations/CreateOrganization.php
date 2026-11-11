<?php

namespace App\Actions\Organizations;

use App\Data\OrganizationData;
use App\Exceptions\UserAlreadyHasOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    /**
     * Create the organization profile and make the user its owner and first member.
     *
     * @throws UserAlreadyHasOrganization
     */
    public function handle(User $user, OrganizationData $data): Organization
    {
        $organization = DB::transaction(function () use ($user, $data): Organization {
            // Lock the user row so two simultaneous submissions cannot both pass the check.
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->organization_id !== null) {
                throw new UserAlreadyHasOrganization;
            }

            $organization = new Organization($data->toAttributes());
            $organization->created_by = $owner->id;
            $organization->save();

            $owner->forceFill(['organization_id' => $organization->id])->save();

            return $organization;
        });

        $user->refresh();

        return $organization;
    }
}
