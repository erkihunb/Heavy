<?php

namespace App\Http\Requests\Registrar;

use App\Enums\Permission;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A Registrar's request made for the applicant of a request at the office:
 * the applicant is the one the `{permit}` in the address was filed for, and
 * their organization's register is the one validated against.
 *
 * @mixin FormRequest
 */
trait ActsForApplicant
{
    protected function applicant(): User
    {
        /** @var Permit $permit */
        $permit = $this->route('permit');

        /** @var User $applicant */
        $applicant = $permit->applicant;

        return $applicant;
    }

    protected function organizationId(): ?int
    {
        return $this->applicant()->organization_id;
    }

    /**
     * The person may register applicants, and the request is an applicant's
     * with an organization.
     */
    protected function mayActForApplicant(): bool
    {
        /** @var Permit|null $permit */
        $permit = $this->route('permit');

        return ($this->user()?->can('registerApplicant', User::class) ?? false)
            && $permit?->applicant !== null
            && $permit->applicant->can(Permission::ApplyForPermits->value)
            && $permit->applicant->organization_id !== null;
    }
}
