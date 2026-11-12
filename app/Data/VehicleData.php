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
        /** What the vehicle is, in words, when its type is "other". */
        public ?string $typeDetail = null,
    ) {}

    /**
     * @return array{vehicle_type_id: int, plate_raw: string, plate_normalized: string, region_code: string, owner_name: string, type_detail?: string}
     */
    public function toAttributes(): array
    {
        // Only given by the office form; an edit elsewhere keeps what was written.
        $detail = $this->typeDetail === null || trim($this->typeDetail) === '' ? [] : ['type_detail' => trim($this->typeDetail)];

        return [
            ...$detail,
            'vehicle_type_id' => $this->vehicleTypeId,
            'plate_raw' => trim($this->plate->raw),
            'plate_normalized' => $this->plate->normalized,
            'region_code' => $this->plate->region->value,
            'owner_name' => $this->ownerName,
        ];
    }
}
