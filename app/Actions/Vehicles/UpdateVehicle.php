<?php

namespace App\Actions\Vehicles;

use App\Data\VehicleData;
use App\Domain\Vehicles\PlateRegistry;
use App\Exceptions\DuplicateVehiclePlate;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UpdateVehicle
{
    public function __construct(private readonly PlateRegistry $plates) {}

    /**
     * Correct the details of a registered vehicle. It never changes organization.
     *
     * @throws DuplicateVehiclePlate
     */
    public function handle(Vehicle $vehicle, VehicleData $data): Vehicle
    {
        $this->plates->ensureAvailable($data->plate, $vehicle->organization_id, $vehicle->id);

        try {
            DB::transaction(fn () => $vehicle->update($data->toAttributes()));
        } catch (UniqueConstraintViolationException) {
            $vehicle->refresh();
            $this->plates->ensureAvailable($data->plate, $vehicle->organization_id, $vehicle->id);

            throw new DuplicateVehiclePlate($data->plate, false);
        }

        return $vehicle;
    }
}
