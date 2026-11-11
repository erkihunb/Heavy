<?php

use App\Support\EthiopianMonth;
use Carbon\CarbonImmutable;

/*
 * The Ethiopian month reports are grouped by: its Gregorian days, its
 * instants in the Authority's timezone, and walking from month to month
 * across Pagume and the new year.
 */

it('finds the month of a date', function (string $date, int $year, int $month) {
    $found = EthiopianMonth::of(CarbonImmutable::parse($date, 'UTC'));

    expect($found->year)->toBe($year)->and($found->month)->toBe($month);
})->with([
    'Meskerem 1, 2018' => ['2025-09-11', 2018, 1],
    'last day of Meskerem 2018' => ['2025-10-10', 2018, 1],
    'Tikimt 1, 2018' => ['2025-10-11', 2018, 2],
    'Pagume 5, 2017' => ['2025-09-10', 2017, 13],
    'Pagume 6, 2015 (leap day)' => ['2023-09-11', 2015, 13],
]);

it('knows its first and last Gregorian day', function () {
    $meskerem = new EthiopianMonth(2018, 1);
    $pagume = new EthiopianMonth(2017, 13);
    $leapPagume = new EthiopianMonth(2015, 13);

    expect($meskerem->firstDay()->toDateString())->toBe('2025-09-11')
        ->and($meskerem->lastDay()->toDateString())->toBe('2025-10-10')
        ->and($pagume->firstDay()->toDateString())->toBe('2025-09-06')
        ->and($pagume->lastDay()->toDateString())->toBe('2025-09-10')
        ->and($leapPagume->lastDay()->toDateString())->toBe('2023-09-11');
});

it('gives the instants of the month in the display timezone, end exclusive', function () {
    config(['localization.display_timezone' => 'Africa/Addis_Ababa']);

    [$start, $end] = (new EthiopianMonth(2018, 1))->instants();

    // Midnight in Addis Ababa (UTC+3) is 21:00 UTC the evening before.
    expect($start->toIso8601String())->toBe('2025-09-10T21:00:00+00:00')
        ->and($end->toIso8601String())->toBe('2025-10-10T21:00:00+00:00');
});

it('walks across Pagume and the new year', function () {
    $pagume = new EthiopianMonth(2017, 13);

    expect($pagume->next())->toEqual(new EthiopianMonth(2018, 1))
        ->and((new EthiopianMonth(2018, 1))->previous())->toEqual($pagume)
        ->and((new EthiopianMonth(2018, 5))->previous())->toEqual(new EthiopianMonth(2018, 4));
});

it('lists the last months oldest first, ending with itself', function () {
    $months = (new EthiopianMonth(2018, 2))->lastMonths(4);

    expect(array_map(fn (EthiopianMonth $month): string => $month->key(), $months))
        ->toBe(['2017-12', '2017-13', '2018-01', '2018-02']);
});

it('takes the current month from the display timezone', function () {
    config(['localization.display_timezone' => 'Africa/Addis_Ababa']);

    // 22:30 UTC on 10 October 2025 is already 11 October (Tikimt 1) in Addis Ababa.
    $this->travelTo(CarbonImmutable::parse('2025-10-10 22:30:00', 'UTC'));

    expect(EthiopianMonth::current())->toEqual(new EthiopianMonth(2018, 2));
});

it('has a label and a sortable key', function () {
    $month = new EthiopianMonth(2018, 2);

    expect($month->key())->toBe('2018-02')
        ->and($month->label('am'))->toBe('ጥቅምት 2018')
        ->and($month->label('en'))->toEndWith(' 2018');
});
