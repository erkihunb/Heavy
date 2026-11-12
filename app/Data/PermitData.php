<?php

namespace App\Data;

use App\Domain\Permits\MovementHours;
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
        /** The branch the applicant applies to; null for the head office. */
        public ?int $branchId = null,
        /** The hours of the day the vehicles may move; null for any time of day. */
        public ?MovementHours $hours = null,
        /** The letter the request came in with; set for requests taken at the office. */
        public ?LetterData $letter = null,
    ) {}

    /**
     * @return array{permit_type: PermitType, reason: string, valid_from: string, valid_to: string, move_from: string|null, move_to: string|null}
     */
    public function toAttributes(): array
    {
        return [
            'permit_type' => $this->permitType,
            'reason' => $this->reason,
            'valid_from' => $this->validFrom->toDateString(),
            'valid_to' => $this->validTo->toDateString(),
            'move_from' => $this->hours?->from,
            'move_to' => $this->hours?->to,
        ];
    }
}
