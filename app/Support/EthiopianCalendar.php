<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Conversion between the Gregorian and Ethiopian (Amete Mihret) calendars.
 *
 * All persistence is Gregorian; this class is the single place where Ethiopian
 * dates are computed for display and input. Conversions go through the Julian
 * Day Number so they are pure integer arithmetic with no timezone involvement:
 * the calendar date (Y-m-d) of the given instant, in its own timezone, is used.
 */
final class EthiopianCalendar
{
    /** Julian Day Number offset of the Amete Mihret era. */
    private const int ERA_OFFSET = 1723856;

    /** @var array<string, list<string>> */
    private const array MONTH_NAMES = [
        'am' => ['መስከረም', 'ጥቅምት', 'ኅዳር', 'ታኅሣሥ', 'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜ'],
        'en' => ['Meskerem', 'Tikimt', 'Hidar', 'Tahsas', 'Tir', 'Yekatit', 'Megabit', 'Miazia', 'Ginbot', 'Sene', 'Hamle', 'Nehase', 'Pagume'],
    ];

    public static function toEthiopian(DateTimeInterface $date): EthiopianDate
    {
        $jdn = self::gregorianToJdn((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));

        $sinceEra = $jdn - self::ERA_OFFSET;
        $cycleDay = $sinceEra % 1461;
        $dayOfYear = ($cycleDay % 365) + 365 * intdiv($cycleDay, 1460);

        return new EthiopianDate(
            year: 4 * intdiv($sinceEra, 1461) + intdiv($cycleDay, 365) - intdiv($cycleDay, 1460),
            month: intdiv($dayOfYear, 30) + 1,
            day: ($dayOfYear % 30) + 1,
        );
    }

    /**
     * @return CarbonImmutable midnight UTC on the corresponding Gregorian date
     *
     * @throws InvalidArgumentException when the Ethiopian date does not exist
     */
    public static function toGregorian(int $year, int $month, int $day): CarbonImmutable
    {
        $date = new EthiopianDate($year, $month, $day);

        $jdn = self::ERA_OFFSET + 365
            + 365 * ($date->year - 1)
            + intdiv($date->year, 4)
            + 30 * $date->month
            + $date->day - 31;

        [$gYear, $gMonth, $gDay] = self::jdnToGregorian($jdn);

        return CarbonImmutable::create($gYear, $gMonth, $gDay, 0, 0, 0, 'UTC');
    }

    public static function isLeapYear(int $year): bool
    {
        return $year % 4 === 3;
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month < 1 || $month > 13) {
            throw new InvalidArgumentException("Invalid Ethiopian month [{$month}].");
        }

        if ($month <= 12) {
            return 30;
        }

        return self::isLeapYear($year) ? 6 : 5;
    }

    public static function monthName(int $month, string $locale = 'am'): string
    {
        if ($month < 1 || $month > 13) {
            throw new InvalidArgumentException("Invalid Ethiopian month [{$month}].");
        }

        return self::monthNames($locale)[$month - 1];
    }

    /**
     * @return list<string> the thirteen month names, Meskerem first
     */
    public static function monthNames(string $locale = 'am'): array
    {
        return self::MONTH_NAMES[$locale] ?? self::MONTH_NAMES['am'];
    }

    /**
     * Numeric Ethiopian date "DD/MM/YYYY".
     */
    public static function format(DateTimeInterface $date): string
    {
        return self::toEthiopian($date)->format();
    }

    /**
     * Labelled Ethiopian date, e.g. "መስከረም 28, 2018".
     */
    public static function label(DateTimeInterface $date, string $locale = 'am'): string
    {
        return self::toEthiopian($date)->label($locale);
    }

    /**
     * Both representations of a date, as sent to the frontend in Resources.
     *
     * @return array{gregorian: string, ethiopian: string, ethiopian_label: string}|null
     */
    public static function present(?DateTimeInterface $date, string $locale = 'am'): ?array
    {
        if ($date === null) {
            return null;
        }

        $ethiopian = self::toEthiopian($date);

        return [
            'gregorian' => $date->format('Y-m-d'),
            'ethiopian' => $ethiopian->format(),
            'ethiopian_label' => $ethiopian->label($locale),
        ];
    }

    private static function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day
            + intdiv(153 * $m + 2, 5)
            + 365 * $y
            + intdiv($y, 4)
            - intdiv($y, 100)
            + intdiv($y, 400)
            - 32045;
    }

    /**
     * @return array{int, int, int} year, month, day
     */
    private static function jdnToGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        return [
            100 * $b + $d - 4800 + intdiv($m, 10),
            $m + 3 - 12 * intdiv($m, 10),
            $e - intdiv(153 * $m + 2, 5) + 1,
        ];
    }
}
