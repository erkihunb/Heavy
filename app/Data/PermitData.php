<?php

namespace App\Data;

use App\Enums\PermitType;
use Carbon\CarbonImmutable;

/**
 * Validated application details, as passed from a FormRequest to an Action.
 */
final readonly class PermitData
{
    /**
     * @param  list<string>  $trailerVehicleIds
     */
    public function __construct(
        public PermitType $permitType,
        public string $primaryVehicleId,
        public array $trailerVehicleIds,
        public CarbonImmutable $validFrom,
        public CarbonImmutable $validTo,
        public string $reason,
    ) {}

    /**
     * @return array{permit_type: PermitType, reason: string, valid_from: string, valid_to: string}
     */
    public function toAttributes(): array
    {
        return [
            'permit_type' => $this->permitType,
            'reason' => $this->reason,
            'valid_from' => $this->validFrom->toDateString(),
            'valid_to' => $this->validTo->toDateString(),
        ];
    }
}
