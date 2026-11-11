<?php

use App\Support\EthiopianCalendar;
use App\Support\EthiopianDate;
use Carbon\CarbonImmutable;

/*
 * Known conversion pairs [ethiopian year, month, day, gregorian ISO date].
 * Each pair was verified by hand against published calendars; they are fixtures,
 * not values produced by the code under test.
 */
dataset('conversion pairs', [
    'new year 2018 (Meskerem 1)' => [2018, 1, 1, '2025-09-11'],
    'permit scheme start (Meskerem 28, 2018)' => [2018, 1, 28, '2025-10-08'],
    'Tahsas 28, 2018' => [2018, 4, 28, '2026-01-06'],
    'Genna (Tahsas 29, 2018)' => [2018, 4, 29, '2026-01-07'],
    'Pagume 1, 2017' => [2017, 13, 1, '2025-09-06'],
    'Pagume 5, 2017 (last day of a common year)' => [2017, 13, 5, '2025-09-10'],
    'Pagume 5, 2015 (leap year)' => [2015, 13, 5, '2023-09-10'],
    'Pagume 6, 2015 (leap day)' => [2015, 13, 6, '2023-09-11'],
    'new year after a leap year (Meskerem 1, 2016)' => [2016, 1, 1, '2023-09-12'],
    'Pagume 6, 2011 (leap day)' => [2011, 13, 6, '2019-09-11'],
    'new year after a leap year (Meskerem 1, 2012)' => [2012, 1, 1, '2019-09-12'],
    'Gregorian leap day 2024' => [2016, 6, 21, '2024-02-29'],
    'Adwa day in a Gregorian leap year' => [2016, 6, 23, '2024-03-02'],
    'Ethiopian millennium' => [2000, 1, 1, '2007-09-12'],
    'new year 2019' => [2019, 1, 1, '2026-09-11'],
]);

it('converts Ethiopian dates to Gregorian', function (int $year, int $month, int $day, string $gregorian) {
    expect(EthiopianCalendar::toGregorian($year, $month, $day)->toDateString())->toBe($gregorian);
})->with('conversion pairs');

it('converts Gregorian dates to Ethiopian', function (int $year, int $month, int $day, string $gregorian) {
    $date = EthiopianCalendar::toEthiopian(CarbonImmutable::parse($gregorian));

    expect([$date->year, $date->month, $date->day])->toBe([$year, $month, $day]);
})->with('conversion pairs');

it('round-trips every day across a four-year leap cycle', function () {
    $day = CarbonImmutable::parse('2023-09-01', 'UTC');
    $end = CarbonImmutable::parse('2027-09-30', 'UTC');

    while ($day->lte($end)) {
        $ethiopian = EthiopianCalendar::toEthiopian($day);
        $back = EthiopianCalendar::toGregorian($ethiopian->year, $ethiopian->month, $ethiopian->day);

        expect($back->toDateString())->toBe($day->toDateString());

        $day = $day->addDay();
    }
});

it('returns a UTC midnight date from toGregorian', function () {
    $date = EthiopianCalendar::toGregorian(2018, 1, 28);

    expect($date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($date->format('Y-m-d H:i:s e'))->toBe('2025-10-08 00:00:00 UTC');
});

it('uses the calendar date of the given instant in its own timezone', function () {
    // 2025-10-07 22:30 UTC is already 2025-10-08 01:30 in Addis Ababa.
    $utc = CarbonImmutable::parse('2025-10-07 22:30:00', 'UTC');

    expect(EthiopianCalendar::toEthiopian($utc)->day)->toBe(27)
        ->and(EthiopianCalendar::toEthiopian($utc->setTimezone('Africa/Addis_Ababa'))->day)->toBe(28);
});

it('knows Ethiopian leap years', function (int $year, bool $leap) {
    expect(EthiopianCalendar::isLeapYear($year))->toBe($leap);
})->with([
    [2011, true],
    [2015, true],
    [2019, true],
    [2016, false],
    [2017, false],
    [2018, false],
]);

it('knows the number of days in each month', function () {
    expect(EthiopianCalendar::daysInMonth(2018, 1))->toBe(30)
        ->and(EthiopianCalendar::daysInMonth(2018, 12))->toBe(30)
        ->and(EthiopianCalendar::daysInMonth(2018, 13))->toBe(5)
        ->and(EthiopianCalendar::daysInMonth(2015, 13))->toBe(6);
});

it('rejects dates that do not exist', function (int $year, int $month, int $day) {
    expect(fn () => EthiopianCalendar::toGregorian($year, $month, $day))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'Pagume 6 in a common year' => [2018, 13, 6],
    'Pagume 7 in a leap year' => [2015, 13, 7],
    'day 31' => [2018, 1, 31],
    'day 0' => [2018, 1, 0],
    'month 14' => [2018, 14, 1],
    'month 0' => [2018, 0, 1],
    'year 0' => [0, 1, 1],
]);

it('formats numeric and labelled dates', function () {
    $date = CarbonImmutable::parse('2025-10-08');

    expect(EthiopianCalendar::format($date))->toBe('28/01/2018')
        ->and(EthiopianCalendar::label($date))->toBe('መስከረም 28, 2018')
        ->and(EthiopianCalendar::label($date, 'en'))->toBe('Meskerem 28, 2018');
});

it('names all thirteen months in Amharic and English', function () {
    expect(EthiopianCalendar::monthName(1))->toBe('መስከረም')
        ->and(EthiopianCalendar::monthName(13))->toBe('ጳጉሜ')
        ->and(EthiopianCalendar::monthName(13, 'en'))->toBe('Pagume')
        ->and(EthiopianCalendar::monthNames('am'))->toHaveCount(13)
        ->and(EthiopianCalendar::monthNames('en'))->toHaveCount(13);
});

it('falls back to Amharic month names for an unknown locale', function () {
    expect(EthiopianCalendar::monthName(1, 'fr'))->toBe('መስከረም');
});

it('presents both calendars for API resources', function () {
    expect(EthiopianCalendar::present(CarbonImmutable::parse('2025-10-08')))->toBe([
        'gregorian' => '2025-10-08',
        'ethiopian' => '28/01/2018',
        'ethiopian_label' => 'መስከረም 28, 2018',
    ])->and(EthiopianCalendar::present(null))->toBeNull();
});

it('parses numeric Ethiopian date strings', function () {
    $date = EthiopianDate::fromString('28/01/2018');

    expect([$date->year, $date->month, $date->day])->toBe([2018, 1, 28])
        ->and((string) $date)->toBe('28/01/2018');
});

it('rejects malformed Ethiopian date strings', function (string $value) {
    expect(fn () => EthiopianDate::fromString($value))->toThrow(InvalidArgumentException::class);
})->with(['', '2018-01-28', '28/01', '31/01/2018', 'abc', '28/01/2018 extra']);
