<?php

use App\Domain\Vehicles\PlateNumber;
use App\Enums\PlateRegion;
use App\Exceptions\InvalidPlateNumber;

it('accepts real plate numbers that are already normalized', function (string $plate, PlateRegion $region, string $code, string $number) {
    $parsed = PlateNumber::parse($plate);

    expect($parsed->normalized)->toBe($plate)
        ->and($parsed->raw)->toBe($plate)
        ->and($parsed->region)->toBe($region)
        ->and($parsed->code)->toBe($code)
        ->and($parsed->number)->toBe($number)
        ->and((string) $parsed)->toBe($plate);
})->with([
    ['አአ-03-A45301', PlateRegion::AddisAbaba, '03', 'A45301'],
    ['ኢት-03-40134', PlateRegion::Federal, '03', '40134'],
    ['ኢት-03-A33490', PlateRegion::Federal, '03', 'A33490'],
    ['ኦሮ-03-B12345', PlateRegion::Oromia, '03', 'B12345'],
]);

it('normalizes messy input to the canonical form', function (string $raw, string $normalized) {
    $parsed = PlateNumber::parse($raw);

    expect($parsed->normalized)->toBe($normalized)
        ->and($parsed->raw)->toBe($raw);
})->with([
    'surrounding whitespace' => ['  ኢት-03-40134 ', 'ኢት-03-40134'],
    'spaces instead of dashes' => ['ኢት 03 40134', 'ኢት-03-40134'],
    'spaces around dashes' => ['ኢት - 03 - A33490', 'ኢት-03-A33490'],
    'en dash' => ['ኢት–03–40134', 'ኢት-03-40134'],
    'em dash' => ['ኢት—03—40134', 'ኢት-03-40134'],
    'minus sign' => ['ኢት−03−40134', 'ኢት-03-40134'],
    'underscore' => ['ኢት_03_40134', 'ኢት-03-40134'],
    'repeated separators' => ['ኢት--03 - -40134', 'ኢት-03-40134'],
    'stray leading and trailing dashes' => ['-አአ-03-A45301-', 'አአ-03-A45301'],
    'lower-case latin letter' => ['አአ-03-a45301', 'አአ-03-A45301'],
    'single digit code is zero padded' => ['አአ-3-A45301', 'አአ-03-A45301'],
    'no separator between region and code' => ['አአ03-A45301', 'አአ-03-A45301'],
    'latin region prefix' => ['AA-03-A45301', 'አአ-03-A45301'],
    'lower-case latin region prefix' => ['et-03-40134', 'ኢት-03-40134'],
    'full-width digits' => ['ኢት-０３-４０１３４', 'ኢት-03-40134'],
]);

it('rejects malformed plate numbers', function (string $raw) {
    expect(fn () => PlateNumber::parse($raw))->toThrow(InvalidPlateNumber::class);
})->with([
    'empty' => [''],
    'whitespace only' => ['   '],
    'number only' => ['40134'],
    'missing number' => ['ኢት-03'],
    'missing code' => ['ኢት-40134'],
    'too many parts' => ['ኢት-03-40134-99'],
    'three digit code' => ['ኢት-123-40134'],
    'number too short' => ['ኢት-03-401'],
    'number too long' => ['ኢት-03-4013499'],
    'letter at the end of the number' => ['ኢት-03-4013A'],
    'two letters in the number' => ['ኢት-03-AB4013'],
    'no separator between code and number' => ['ኢት-0340134'],
    'two plates in one value' => ['ኢት-03-A33490/ ኢት-03-40134'],
    'free text' => ['የጭነት ተሽከርካሪ'],
    'markup' => ['<b>ኢት-03-40134</b>'],
]);

it('rejects an unknown region prefix', function (string $raw) {
    expect(fn () => PlateNumber::parse($raw))->toThrow(InvalidPlateNumber::class, 'unknown region');
})->with(['XX-03-40134', 'ሀለ-03-40134']);

it('returns null from tryParse instead of throwing', function () {
    expect(PlateNumber::tryParse('not a plate'))->toBeNull()
        ->and(PlateNumber::tryParse('ኢት-03-40134'))->toBeInstanceOf(PlateNumber::class);
});

it('treats differently typed spellings of one plate as equal', function () {
    $a = PlateNumber::parse('አአ-03-A45301');
    $b = PlateNumber::parse(' aa 3 a45301 ');
    $c = PlateNumber::parse('አአ-03-A45302');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});

it('resolves every region from both its Ethiopic and Latin prefix', function (PlateRegion $region) {
    expect(PlateRegion::fromPrefix($region->value))->toBe($region)
        ->and(PlateRegion::fromPrefix($region->latinCode()))->toBe($region);
})->with(PlateRegion::cases());

it('has no two regions sharing a Latin prefix', function () {
    $codes = array_map(fn (PlateRegion $region) => $region->latinCode(), PlateRegion::cases());

    expect(array_unique($codes))->toHaveCount(count($codes));
});
