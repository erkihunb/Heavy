<?php

namespace App\Actions\Vehicles;

use App\Data\VehicleData;
use App\Domain\Vehicles\PlateRegistry;
use App\Exceptions\DuplicateVehiclePlate;
use App\Models\Organization;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RegisterVehicle
{
    public function __construct(private readonly PlateRegistry $plates) {}

    /**
     * Add a vehicle to the register of an organization.
     *
     * @throws DuplicateVehiclePlate
     */
    public function handle(Organization $organization, VehicleData $data): Vehicle
    {
        $this->plates->ensureAvailable($data->plate, $organization->id);

        try {
            return DB::transaction(function () use ($organization, $data): Vehicle {
                $vehicle = new Vehicle($data->toAttributes());
                $vehicle->organization_id = $organization->id;
                $vehicle->save();

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request registered the same plate between the check and the
            // insert; the unique index is the final authority.
            $this->plates->ensureAvailable($data->plate, $organization->id);

            throw new DuplicateVehiclePlate($data->plate, false);
        }
    }
}
