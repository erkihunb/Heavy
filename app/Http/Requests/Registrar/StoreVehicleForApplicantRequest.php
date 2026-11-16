<?php

namespace App\Http\Requests\Registrar;

use App\Data\VehicleData;
use App\Domain\Vehicles\PlateNumber;
use App\Domain\Vehicles\PlateRegistry;
use App\Exceptions\DuplicateVehiclePlate;
use App\Models\VehicleType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Registrar adds a vehicle to the register of the applicant a request at
 * the office was filed for.
 */
class StoreVehicleForApplicantRequest extends FormRequest
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
            'plate' => ['bail', 'required', 'string', 'max:50', $this->validPlate(...)],
            // Only active types may be chosen.
            'vehicle_type_id' => ['required', 'integer', Rule::exists(VehicleType::class, 'id')->where('is_active', true)],
            'owner_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plate' => __('vehicles.fields.plate'),
            'vehicle_type_id' => __('vehicles.fields.vehicle_type'),
            'owner_name' => __('vehicles.fields.owner_name'),
        ];
    }

    public function toData(): VehicleData
    {
        /** @var array{plate: string, vehicle_type_id: int|string, owner_name: string} $validated */
        $validated = $this->validated();

        return new VehicleData(
            plate: PlateNumber::parse($validated['plate']),
            vehicleTypeId: (int) $validated['vehicle_type_id'],
            ownerName: $validated['owner_name'],
        );
    }

    /**
     * The plate must be well formed and not already in the register.
     */
    private function validPlate(string $attribute, mixed $value, Closure $fail): void
    {
        $plate = PlateNumber::tryParse((string) $value);

        if ($plate === null) {
            $fail(__('vehicles.errors.plate_invalid'));

            return;
        }

        $organizationId = $this->organizationId();

        if ($organizationId === null) {
            return;
        }

        try {
            app(PlateRegistry::class)->ensureAvailable($plate, $organizationId, null);
        } catch (DuplicateVehiclePlate $duplicate) {
            $fail($duplicate->userMessage());
        }
    }
}
