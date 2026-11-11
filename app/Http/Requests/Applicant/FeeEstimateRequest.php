<?php

namespace App\Http\Requests\Applicant;

use App\Enums\PermitType;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the application form sends to ask what a permit would cost today.
 */
class FeeEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Permit::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'permit_type' => ['required', Rule::enum(PermitType::class)],
            'primary_vehicle_id' => ['bail', 'required', 'string', Rule::exists(Vehicle::class, 'id')
                ->where('organization_id', $this->user()->organization_id ?? 0)],
            'valid_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'valid_to' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ];
    }
}
