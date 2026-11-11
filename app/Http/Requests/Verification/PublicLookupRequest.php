<?php

namespace App\Http\Requests\Verification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Checking a permit by its number. The plate is required as well, so permit
 * numbers cannot be tried one after another (spec §7).
 */
class PublicLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'permit_number' => ['required', 'string', 'max:32'],
            'plate' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'permit_number' => __('verification.fields.permit_number'),
            'plate' => __('verification.fields.plate'),
        ];
    }
}
