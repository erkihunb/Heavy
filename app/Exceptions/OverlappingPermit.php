<?php

namespace App\Exceptions;

use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * A vehicle is already reserved by another permit for some of the requested
 * days (spec §4.1). It names the plate and the days that are taken, and
 * deliberately nothing about the other permit or the organization holding it.
 */
final class OverlappingPermit extends PermitException
{
    public function __construct(
        public readonly ?string $plate = null,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
    ) {
        parent::__construct(
            $plate === null
                ? 'A vehicle already has a permit for an overlapping period.'
                : "Vehicle [{$plate}] already has a permit for an overlapping period.",
        );
    }

    public function userMessage(): string
    {
        if ($this->plate === null || $this->from === null || $this->to === null) {
            return __('permits.errors.overlap_generic');
        }

        $locale = app()->getLocale();

        return __('permits.errors.overlap', [
            'plate' => $this->plate,
            'from' => EthiopianCalendar::label($this->from, $locale),
            'to' => EthiopianCalendar::label($this->to, $locale),
        ]);
    }
}
