<?php

namespace App\Http\Requests\Applicant;

use App\Models\Organization;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateOrganizationRequest extends OrganizationRequest
{
    /**
     * The organization is always the user's own; it is never taken from the request.
     */
    public function authorize(): bool
    {
        $organization = $this->organization();

        return $organization !== null && (bool) $this->user()?->can('update', $organization);
    }

    public function organization(): ?Organization
    {
        return $this->user()?->organization;
    }

    protected function uniqueTin(): Unique
    {
        return Rule::unique(Organization::class, 'tin')->ignore($this->organization()?->id);
    }
}
