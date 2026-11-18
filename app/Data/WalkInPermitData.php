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
        /** Where the vehicles may travel, in words; null when the permit does not limit it. */
        public ?string $route = null,
    ) {}

    /**
     * The same request for another vehicle of the same letter: the letter's
     * scan is stored once, with the first permit, and shared by the others.
     */
    public function forAnotherVehicle(): self
    {
        $letter = $this->letter === null ? null : new LetterData($this->letter->number, $this->letter->date);

        return new self($this->permitType, $this->validFrom, $this->validTo, $this->reason, $this->submit, $this->hours, $letter, $this->route);
    }

    /**
     * @param  list<string>  $trailerVehicleIds
     */
    public function withVehicles(string $primaryVehicleId, array $trailerVehicleIds): PermitData
    {
        return new PermitData($this->permitType, $primaryVehicleId, $trailerVehicleIds, $this->validFrom, $this->validTo, $this->reason, hours: $this->hours, letter: $this->letter, route: $this->route);
    }
}
