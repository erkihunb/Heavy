<?php

namespace App\Actions\Vehicles;

use App\Data\VehicleData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Vehicles\PlateRegistry;
use App\Exceptions\DuplicateVehiclePlate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Correct a vehicle's plate, type and owner while the request it is on is
 * still open (PermitPolicy::updateVehicleFor decides when), with the old and
 * new details in the audit log.
 */
class CorrectVehicle
{
    public function __construct(
        private readonly PlateRegistry $plates,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @throws DuplicateVehiclePlate
     */
    public function handle(Vehicle $vehicle, VehicleData $data, User $actor): Vehicle
    {
        $this->plates->ensureAvailable($data->plate, $vehicle->organization_id, $vehicle->id);

        try {
            return DB::transaction(function () use ($vehicle, $data, $actor): Vehicle {
                $fields = ['plate_normalized', 'vehicle_type_id', 'owner_name'];
                $old = $vehicle->only($fields);

                $vehicle->fill($data->toAttributes());

                if ($vehicle->isDirty()) {
                    $vehicle->save();
                    $this->audit->record(AuditEvent::VehicleCorrected, $vehicle, $actor, old: $old, new: $vehicle->only($fields));
                }

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException) {
            $this->plates->ensureAvailable($data->plate, $vehicle->organization_id, $vehicle->id);

            throw new DuplicateVehiclePlate($data->plate, false);
        }
    }
}
