<?php

namespace App\Http\Requests\Applicant;

use App\Models\Organization;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreOrganizationRequest extends OrganizationRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Organization::class);
    }

    protected function uniqueTin(): Unique
    {
        return Rule::unique(Organization::class, 'tin');
    }
}
