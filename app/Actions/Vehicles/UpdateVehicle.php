<?php

namespace App\Actions\Vehicles;

use App\Data\VehicleData;
use App\Domain\Vehicles\PlateRegistry;
use App\Exceptions\DuplicateVehiclePlate;
use App\Exceptions\VehiclePlateLocked;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UpdateVehicle
{
    public function __construct(private readonly PlateRegistry $plates) {}

    /**
     * Correct the details of a registered vehicle. It never changes organization,
     * and its plate is fixed once it is on a submitted application.
     *
     * @throws DuplicateVehiclePlate
     * @throws VehiclePlateLocked
     */
    public function handle(Vehicle $vehicle, VehicleData $data): Vehicle
    {
        $this->plates->ensureAvailable($data->plate, $vehicle->organization_id, $vehicle->id);

        try {
            DB::transaction(function () use ($vehicle, $data): void {
                if ($data->plate->normalized !== $vehicle->plate_normalized && $vehicle->hasLockedPlate()) {
                    throw new VehiclePlateLocked($vehicle->plate_normalized);
                }

                $vehicle->update($data->toAttributes());
            });
        } catch (UniqueConstraintViolationException) {
            $vehicle->refresh();
            $this->plates->ensureAvailable($data->plate, $vehicle->organization_id, $vehicle->id);

            throw new DuplicateVehiclePlate($data->plate, false);
        }

        return $vehicle;
    }
}
