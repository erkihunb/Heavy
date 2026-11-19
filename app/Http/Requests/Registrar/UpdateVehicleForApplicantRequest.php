<?php

namespace App\Http\Requests\Registrar;

use App\Data\VehicleData;
use App\Domain\Vehicles\PlateNumber;
use App\Domain\Vehicles\PlateRegistry;
use App\Exceptions\DuplicateVehiclePlate;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A Registrar corrects a vehicle of a request registered at the office while
 * the request is a draft or returned for changes: its plate, type and owner.
 */
class UpdateVehicleForApplicantRequest extends FormRequest
{
    use ActsForApplicant;

    public function authorize(): Response
    {
        /** @var Permit $permit */
        $permit = $this->route('permit');

        return Gate::inspect('updateVehicleFor', [$permit, $this->applicant(), $this->vehicle()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plate' => ['bail', 'required', 'string', 'max:50', $this->validPlate(...)],
            // An active type, or the one the vehicle already has.
            'vehicle_type_id' => ['required', 'integer', Rule::exists(VehicleType::class, 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhere('id', $this->vehicle()->vehicle_type_id))],
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

    public function vehicle(): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return $vehicle;
    }

    /**
     * The plate must be well formed and not on another vehicle in the register.
     */
    private function validPlate(string $attribute, mixed $value, Closure $fail): void
    {
        $plate = PlateNumber::tryParse((string) $value);

        if ($plate === null) {
            $fail(__('vehicles.errors.plate_invalid'));

            return;
        }

        try {
            app(PlateRegistry::class)->ensureAvailable($plate, $this->vehicle()->organization_id, $this->vehicle()->id);
        } catch (DuplicateVehiclePlate $duplicate) {
            $fail($duplicate->userMessage());
        }
    }
}
