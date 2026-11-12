<?php

namespace App\Data;

use App\Domain\Permits\MovementHours;
use App\Enums\PermitType;
use Carbon\CarbonImmutable;

/**
 * The permit part of the office's one-page form: what an application needs
 * besides its vehicles, which do not exist yet when the form is sent.
 */
final readonly class WalkInPermitData
{
    public function __construct(
        public PermitType $permitType,
        public CarbonImmutable $validFrom,
        public CarbonImmutable $validTo,
        public string $reason,
        /** Hand it to the Authority now, instead of keeping a draft. */
        public bool $submit,
        /** The hours of the day the vehicles may move; null for any time of day. */
        public ?MovementHours $hours = null,
        /** The letter the request came in with. */
        public ?LetterData $letter = null,
    ) {}

    /**
     * @param  list<string>  $trailerVehicleIds
     */
    public function withVehicles(string $primaryVehicleId, array $trailerVehicleIds): PermitData
    {
        return new PermitData($this->permitType, $primaryVehicleId, $trailerVehicleIds, $this->validFrom, $this->validTo, $this->reason, hours: $this->hours, letter: $this->letter);
    }
}
