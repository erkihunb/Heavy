<?php

namespace App\Domain\Institution;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Authority's own vehicles. They belong to one organization, the
 * Authority itself (`organizations.is_institution`), made the first time a
 * vehicle is registered and named after the Authority; nobody applies on its
 * behalf and it never appears among the applicants' organizations to pay.
 */
final class InstitutionFleet
{
    /**
     * The Authority's own organization, when it has one yet.
     */
    public static function organization(): ?Organization
    {
        return Organization::query()->where('is_institution', true)->orderBy('id')->first();
    }

    /**
     * The Authority's own organization, opened by this member of staff when it is not there yet.
     */
    public static function organizationFor(User $staff): Organization
    {
        $organization = self::organization();

        if ($organization !== null) {
            return $organization;
        }

        $organization = new Organization([
            'name_am' => __('common.authority', [], 'am'),
            'name_en' => __('common.authority', [], 'en'),
            'phone' => (string) (config('permits.branding.support_phone') ?: '+251000000000'),
            'email' => (string) (config('permits.branding.support_email') ?: config('mail.from.address', 'office@example.test')),
            'address' => __('common.authority', [], 'am'),
        ]);
        $organization->is_institution = true;
        $organization->created_by = $staff->id;
        $organization->save();

        return $organization;
    }

    /**
     * The registered vehicles of the Authority.
     *
     * @return Builder<Vehicle>
     */
    public static function vehicles(): Builder
    {
        $id = self::organization()?->id;

        return Vehicle::query()->where('organization_id', $id ?? 0);
    }
}
