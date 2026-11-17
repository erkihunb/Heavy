<?php

namespace App\Http\Requests\Registrar;

use App\Enums\PermitType;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the application form sends to ask which fee rates a permit can be
 * registered at today, for the applicant a request at the office was filed
 * for: the permit type and the primary vehicle, one of the organization's.
 */
class FeeRatesForApplicantRequest extends FormRequest
{
    use ActsForApplicant;

    public function authorize(): bool
    {
        return $this->mayActForApplicant();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'permit_type' => ['required', Rule::enum(PermitType::class)],
            'primary_vehicle_id' => ['bail', 'required', 'string', Rule::exists(Vehicle::class, 'id')
                ->where('organization_id', $this->organizationId() ?? 0)],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
