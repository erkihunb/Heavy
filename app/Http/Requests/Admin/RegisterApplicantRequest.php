<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Applicant\OrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * An applicant registered at the office: the contact person (name, e-mail,
 * language) and the organization, validated as the applicant's own form is.
 */
class RegisterApplicantRequest extends OrganizationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registerApplicant', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['contact_email', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => mb_strtolower(trim($this->input($field)))]);
            }
        }
    }

    protected function uniqueTin(): Unique
    {
        return Rule::unique(Organization::class, 'tin');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('localization.supported')))],
            ...parent::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contact_name' => __('applicants.fields.contact_name'),
            'contact_email' => __('applicants.fields.contact_email'),
            'locale' => __('applicants.fields.locale'),
            ...parent::attributes(),
        ];
    }
}
