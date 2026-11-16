<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * A validated calendar date in the Ethiopian calendar (Amete Mihret era).
 */
final readonly class EthiopianDate implements Stringable
{
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
        if ($year < 1) {
            throw new InvalidArgumentException("Invalid Ethiopian year [{$year}].");
        }

        if ($month < 1 || $month > 13) {
            throw new InvalidArgumentException("Invalid Ethiopian month [{$month}].");
        }

        if ($day < 1 || $day > EthiopianCalendar::daysInMonth($year, $month)) {
            throw new InvalidArgumentException("Invalid Ethiopian date [{$day}/{$month}/{$year}].");
        }
    }

    /**
     * Numeric form "DD/MM/YYYY".
     */
    public function format(): string
    {
        return sprintf('%02d/%02d/%04d', $this->day, $this->month, $this->year);
    }

    /**
     * Human-readable form, e.g. "መስከረም 28, 2018".
     */
    public function label(string $locale = 'am'): string
    {
        return sprintf('%s %d, %d', EthiopianCalendar::monthName($this->month, $locale), $this->day, $this->year);
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
