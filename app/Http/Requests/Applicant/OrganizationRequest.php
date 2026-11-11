<?php

namespace App\Http\Requests\Applicant;

use App\Data\OrganizationData;
use App\Support\EthiopianPhone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation shared by creating and updating the organization profile.
 */
abstract class OrganizationRequest extends FormRequest
{
    /**
     * The uniqueness rule for the TIN (updating ignores the organization itself).
     */
    abstract protected function uniqueTin(): Unique;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name_am' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'regex:/^\d{10}$/', $this->uniqueTin()],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (EthiopianPhone::normalize((string) $value) === null) {
                    $fail(__('organization.errors.phone_invalid'));
                }
            }],
            'email' => ['required', 'string', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tin.regex' => __('organization.errors.tin_format'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_am' => __('organization.fields.name_am'),
            'name_en' => __('organization.fields.name_en'),
            'tin' => __('organization.fields.tin'),
            'phone' => __('organization.fields.phone'),
            'email' => __('organization.fields.email'),
            'address' => __('organization.fields.address'),
        ];
    }

    public function toData(): OrganizationData
    {
        /** @var array{name_am: string, name_en?: string|null, tin?: string|null, phone: string, email: string, address: string} $validated */
        $validated = $this->validated();

        return new OrganizationData(
            nameAm: $validated['name_am'],
            nameEn: $validated['name_en'] ?? null,
            tin: $validated['tin'] ?? null,
            phone: (string) EthiopianPhone::normalize($validated['phone']),
            email: $validated['email'],
            address: $validated['address'],
        );
    }
}
