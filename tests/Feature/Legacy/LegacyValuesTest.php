<?php

use App\Domain\Legacy\InvalidLegacyValue;
use App\Domain\Legacy\LegacyImporter;
use App\Domain\Legacy\LegacyValues;
use App\Enums\PermitType;

/*
 * Reading the free-text values of the legacy Google Form (spec §13), with
 * the spellings found in the real sheet.
 */

it('reads every spelling of "ሙሉ ቀን" as full day and "ይለፍ" as pass', function (string $raw, PermitType $type) {
    expect(LegacyValues::permitType($raw))->toBe($type);
})->with([
    ['ሙሉ ቀን', PermitType::FullDay],
    ['ሙሉ ቀን_____', PermitType::FullDay],
    ['ሙሉ ቀን/', PermitType::FullDay],
    [' ሙሉ ቀን', PermitType::FullDay],
    ['ሙሉ ቀ', PermitType::FullDay],
    ['ሙሉቀን', PermitType::FullDay],
    ['ይለፍ', PermitType::Pass],
    ['ይለፍ_', PermitType::Pass],
    [' ይለፍ ', PermitType::Pass],
]);

it('refuses a permit type that is neither', function (string $raw) {
    expect(fn () => LegacyValues::permitType($raw))->toThrow(InvalidLegacyValue::class);
})->with(['', 'ሌላ', 'night']);

it('maps vehicle types through the reviewed mapping file', function (string $raw, ?string $code) {
    $map = require database_path('import/vehicle_type_map.php');

    expect(LegacyValues::vehicleTypeCode($raw, $map))->toBe($code);
})->with([
    ['የጭነት', 'cargo'],
    [' የጭነት ', 'cargo'],
    ['ጭነት', 'cargo'],
    ['ልዩ ተንቀሳቃሽ', 'special_mobile'],
    ['ልዩ ተንቀሳቃሸ', 'special_mobile'],
    ['ገልባጭ', null],
]);

it('splits a tractor and its trailer', function (string $raw, array $plates) {
    expect(LegacyValues::plates($raw))->toBe($plates);
})->with([
    ['ኢት-03-A33490/ ኢት-03-40134', ['ኢት-03-A33490', 'ኢት-03-40134']],
    ['አአ-03-A45301', ['አአ-03-A45301']],
    [' አአ-03-1 / / አአ-03-2 ', ['አአ-03-1', 'አአ-03-2']],
]);

it('converts the Ethiopian validity text to Gregorian days', function (string $raw, string $from, string $to) {
    [$start, $end] = LegacyValues::validity($raw);

    expect($start->toDateString())->toBe($from)
        ->and($end->toDateString())->toBe($to);
})->with([
    ['28/01/2018 አስከ 28/04/2018', '2025-10-08', '2026-01-06'],
    ['28/01/2018 እስከ 28/04/2018', '2025-10-08', '2026-01-06'],
    ['1/3/2018 - 30/3/2018', '2025-11-10', '2025-12-09'],
    ['01/13/2017 እስከ 05/13/2017', '2025-09-06', '2025-09-10'],
]);

it('explains a validity it cannot read', function (string $raw, string $message) {
    expect(fn () => LegacyValues::validity($raw))->toThrow(InvalidLegacyValue::class, $message);
})->with([
    ['', 'empty'],
    ['28/01/2018', 'two dates'],
    ['31/01/2018 አስከ 05/02/2018', 'not a date in the Ethiopian calendar'],
    ['06/13/2018 አስከ 06/13/2018', 'not a date in the Ethiopian calendar'],
    ['28/01/1018 አስከ 28/04/1018', 'not a plausible Ethiopian year'],
    ['28/04/2018 እስከ 28/01/2018', 'after the last day'],
]);

it('reads the amount paid', function (string $raw, ?int $cents) {
    expect(LegacyValues::amountCents($raw))->toBe($cents);
})->with([
    ['1000', 100_000],
    ['6,000 ብር', 600_000],
    ['ETB 15,000.00', 1_500_000],
    ['', null],
]);

it('refuses an amount that is not a number', function () {
    expect(fn () => LegacyValues::amountCents('abc'))->toThrow(InvalidLegacyValue::class);
});

it('normalises receipt numbers', function () {
    expect(LegacyValues::receipt('fs 228741'))->toBe('FS228741')
        ->and(LegacyValues::receipt(''))->toBeNull()
        ->and(fn () => LegacyValues::receipt('#?'))->toThrow(InvalidLegacyValue::class);
});

it('reads the form timestamp as Addis Ababa time', function () {
    $moment = LegacyValues::timestamp('10/8/2025 9:15:02', LegacyImporter::DEFAULT_TIMESTAMP_FORMATS);

    expect($moment->toIso8601String())->toBe('2025-10-08T06:15:02+00:00');
});

it('refuses a timestamp it cannot read', function () {
    expect(fn () => LegacyValues::timestamp('sometime in October', LegacyImporter::DEFAULT_TIMESTAMP_FORMATS))
        ->toThrow(InvalidLegacyValue::class);
});

it('recognises the same organization however it is spaced or punctuated', function () {
    expect(LegacyValues::organization('አበበ  የጭነት ማጓጓዣ .')['key'])->toBe(LegacyValues::organization('አበበ የጭነት ማጓጓዣ')['key'])
        ->and(LegacyValues::organization('  አበበ  የጭነት ማጓጓዣ . ')['name'])->toBe('አበበ የጭነት ማጓጓዣ');
});

it('identifies a row by its timestamp, e-mail and plate cell', function () {
    expect(LegacyValues::sourceRef('10/8/2025 9:15:02', 'A@x.com', 'አአ-03-1'))
        ->toBe(LegacyValues::sourceRef(' 10/8/2025 9:15:02 ', 'a@x.com', 'አአ-03-1'))
        ->not->toBe(LegacyValues::sourceRef('10/8/2025 9:15:02', 'a@x.com', 'አአ-03-2'));
});
