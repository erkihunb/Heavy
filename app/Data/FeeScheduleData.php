<?php

namespace App\Data;

use App\Enums\PermitType;
use Carbon\CarbonImmutable;

/**
 * A validated fee schedule line, as an Admin enters it.
 */
final readonly class FeeScheduleData
{
    public function __construct(
        public PermitType $permitType,
        /** null: every vehicle type without a fee of its own. */
        public ?int $vehicleTypeId,
        public int $minDays,
        public int $maxDays,
        /** Santim. */
        public int $amountCents,
        public CarbonImmutable $effectiveFrom,
        public ?CarbonImmutable $effectiveTo,
    ) {}
}
