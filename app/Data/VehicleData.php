<?php

namespace App\Data;

use App\Domain\Vehicles\PlateNumber;

/**
 * Validated vehicle details, as passed from a FormRequest to an Action.
 */
final readonly class VehicleData
{
    public function __construct(
        public PlateNumber $plate,
        public int $vehicleTypeId,
        public string $ownerName,
    ) {}

    /**
     * @return array{vehicle_type_id: int, plate_raw: string, plate_normalized: string, region_code: string, owner_name: string}
     */
    public function toAttributes(): array
    {
        return [
            'vehicle_type_id' => $this->vehicleTypeId,
            'plate_raw' => trim($this->plate->raw),
            'plate_normalized' => $this->plate->normalized,
            'region_code' => $this->plate->region->value,
            'owner_name' => $this->ownerName,
        ];
    }
}
