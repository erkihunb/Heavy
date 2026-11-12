<?php

namespace App\Http\Requests\Registrar;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A Registrar's request made for an applicant who came to the office: the
 * applicant is the `{applicant}` in the address, and their organization's
 * register is the one validated against, exactly as for the applicant.
 *
 * @mixin FormRequest
 */
trait ActsForApplicant
{
    protected function applicant(): User
    {
        /** @var User $applicant */
        $applicant = $this->route('applicant');

        return $applicant;
    }

    protected function organizationId(): ?int
    {
        return $this->applicant()->organization_id;
    }

    /**
     * The person may register applicants, and the one in the address is an
     * applicant with an organization.
     */
    protected function mayActForApplicant(): bool
    {
        return ($this->user()?->can('registerApplicant', User::class) ?? false)
            && $this->applicant()->can(Permission::ApplyForPermits->value)
            && $this->applicant()->organization_id !== null;
    }
}
