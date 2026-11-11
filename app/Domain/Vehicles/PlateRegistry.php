<?php

namespace App\Domain\Vehicles;

use App\Exceptions\DuplicateVehiclePlate;
use App\Models\Vehicle;

/**
 * Answers the one question the register must get right: is this plate free?
 */
final class PlateRegistry
{
    /**
     * @param  string|null  $exceptVehicleId  the vehicle being edited, which may keep its own plate
     *
     * @throws DuplicateVehiclePlate
     */
    public function ensureAvailable(PlateNumber $plate, int $organizationId, ?string $exceptVehicleId = null): void
    {
        $existing = Vehicle::query()
            ->where('plate_normalized', $plate->normalized)
            ->when($exceptVehicleId !== null, fn ($query) => $query->whereKeyNot($exceptVehicleId))
            ->first(['id', 'organization_id']);

        if ($existing !== null) {
            throw new DuplicateVehiclePlate($plate, $existing->organization_id === $organizationId);
        }
    }
}
