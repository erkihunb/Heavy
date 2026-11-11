<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * A month of the Ethiopian calendar, the unit reports are grouped by
 * (spec §11: "issued this E.C. month", "revenue by E.C. month"). Twelve
 * months of 30 days and Pagume of 5 or 6.
 */
final readonly class EthiopianMonth
{
    public function __construct(public int $year, public int $month) {}

    /**
     * The Ethiopian month the calendar date of the given moment falls in, in
     * that moment's own timezone.
     */
    public static function of(DateTimeInterface $date): self
    {
        $ethiopian = EthiopianCalendar::toEthiopian($date);

        return new self($ethiopian->year, $ethiopian->month);
    }

    /**
     * The month of today in the display timezone.
     */
    public static function current(): self
    {
        return self::of(CarbonImmutable::now((string) config('localization.display_timezone')));
    }

    /**
     * First Gregorian day of the month (midnight UTC, like all calendar dates here).
     */
    public function firstDay(): CarbonImmutable
    {
        return EthiopianCalendar::toGregorian($this->year, $this->month, 1);
    }

    /**
     * Last Gregorian day of the month (midnight UTC).
     */
    public function lastDay(): CarbonImmutable
    {
        return EthiopianCalendar::toGregorian($this->year, $this->month, EthiopianCalendar::daysInMonth($this->year, $this->month));
    }

    /**
     * The month as an instant range for timestamp columns stored in UTC:
     * from the first day's midnight to the day after the last, in the display timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [start inclusive, end exclusive], UTC
     */
    public function instants(): array
    {
        $timezone = (string) config('localization.display_timezone');

        return [
            CarbonImmutable::parse($this->firstDay()->toDateString(), $timezone)->utc(),
            CarbonImmutable::parse($this->lastDay()->addDay()->toDateString(), $timezone)->utc(),
        ];
    }

    public function previous(): self
    {
        return $this->month === 1 ? new self($this->year - 1, 13) : new self($this->year, $this->month - 1);
    }

    public function next(): self
    {
        return $this->month === 13 ? new self($this->year + 1, 1) : new self($this->year, $this->month + 1);
    }

    /**
     * The given number of months ending with this one, oldest first.
     *
     * @return list<self>
     */
    public function lastMonths(int $count): array
    {
        $months = [$this];

        while (count($months) < $count) {
            array_unshift($months, $months[0]->previous());
        }

        return $months;
    }

    /**
     * "ጥቅምት 2018" / "Tikimt 2018".
     */
    public function label(string $locale): string
    {
        return EthiopianCalendar::monthName($this->month, $locale).' '.$this->year;
    }

    /**
     * "2018-02", sortable.
     */
    public function key(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
