<?php

namespace App\Http\Requests\Applicant;

use App\Data\VehicleData;
use App\Domain\Vehicles\PlateNumber;
use App\Domain\Vehicles\PlateRegistry;
use App\Exceptions\DuplicateVehiclePlate;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation shared by registering and updating a vehicle.
 */
abstract class VehicleRequest extends FormRequest
{
    /**
     * The vehicle being edited, or null when registering a new one.
     */
    abstract protected function vehicle(): ?Vehicle;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currentTypeId = $this->vehicle()?->vehicle_type_id;

        return [
            'plate' => ['bail', 'required', 'string', 'max:50', $this->validPlate(...)],
            'vehicle_type_id' => [
                'required',
                'integer',
                // Only active types may be chosen; a vehicle may keep a type that was retired later.
                Rule::exists(VehicleType::class, 'id')->where(fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->when($currentTypeId !== null, fn (Builder $query) => $query->orWhere('id', $currentTypeId)))),
            ],
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

    /**
     * The organization whose register the request is about: the signed-in
     * applicant's own. A Registrar acting for an applicant overrides it.
     */
    protected function organizationId(): ?int
    {
        return $this->user()?->organization_id;
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
     * The plate must be well formed, not already in the register, and may not
     * change once the vehicle is on a submitted application.
     */
    private function validPlate(string $attribute, mixed $value, Closure $fail): void
    {
        $plate = PlateNumber::tryParse((string) $value);

        if ($plate === null) {
            $fail(__('vehicles.errors.plate_invalid'));

            return;
        }

        $vehicle = $this->vehicle();

        if ($vehicle !== null && $plate->normalized !== $vehicle->plate_normalized && $vehicle->hasLockedPlate()) {
            $fail(__('vehicles.errors.plate_locked'));

            return;
        }

        $organizationId = $this->vehicle()->organization_id ?? $this->organizationId();

        if ($organizationId === null) {
            return;
        }

        try {
            app(PlateRegistry::class)->ensureAvailable($plate, $organizationId, $this->vehicle()?->id);
        } catch (DuplicateVehiclePlate $duplicate) {
            $fail($duplicate->userMessage());
        }
    }
}
