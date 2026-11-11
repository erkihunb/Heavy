<?php

use App\Domain\Payments\ReceiptNumber;
use App\Support\Money;

/*
 * Money is integer santim from the moment it is typed (spec §4.4); receipt
 * numbers are compared in one canonical form (spec §4.5).
 */

describe('Money::parse', function () {
    it('turns birr typed as text into santim', function (string $typed, int $santim) {
        expect(Money::parse($typed))->toBe($santim);
    })->with([
        'whole birr' => ['1500', 150_000],
        'one decimal' => ['1500.5', 150_050],
        'two decimals' => ['1500.50', 150_050],
        'santim only' => ['0.05', 5],
        'thousands separators' => ['15,000', 1_500_000],
        'surrounding spaces' => ['  6000 ', 600_000],
        'zero' => ['0', 0],
        'largest' => ['999999999.99', 99_999_999_999],
    ]);

    it('never goes through a float', function () {
        // 0.1 + 0.2 style errors: 19.99 as a float is 1998.9999…
        expect(Money::parse('19.99'))->toBe(1999)
            ->and(Money::parse('1234567.89'))->toBe(123_456_789);
    });

    it('refuses text that is not an amount', function (string $typed) {
        expect(Money::isValid($typed))->toBeFalse()
            ->and(fn () => Money::parse($typed))->toThrow(InvalidArgumentException::class);
    })->with(['', 'abc', '-100', '1.234', '1e5', '1500.', '.50', '1000000000', '15 00 birr']);
});

describe('Money::format', function () {
    it('formats santim for people', function (int $santim, string $decimal) {
        expect(Money::decimal($santim))->toBe($decimal)
            ->and(Money::format($santim))->toBe("ETB {$decimal}");
    })->with([
        [150_000, '1,500.00'],
        [150_050, '1,500.50'],
        [5, '0.05'],
        [0, '0.00'],
        [1_500_000, '15,000.00'],
    ]);

    it('presents an amount with its currency for the frontend', function () {
        expect(Money::present(600_000))->toBe([
            'amount_cents' => 600_000,
            'currency' => 'ETB',
            'formatted' => 'ETB 6,000.00',
        ]);
    });

    it('round-trips', function (int $santim) {
        expect(Money::parse(Money::decimal($santim)))->toBe($santim);
    })->with([0, 1, 99, 100, 150_050, 99_999_999_999]);
});

describe('ReceiptNumber', function () {
    it('normalises the ways one receipt is written', function (string $typed) {
        expect(ReceiptNumber::normalize($typed))->toBe('FS-228740');
    })->with(['FS-228740', 'fs-228740', ' FS-228740 ', 'FS - 228740', "FS\u{2013}228740", "fs\u{2212}228740"]);

    it('drops spaces inside the number', function () {
        expect(ReceiptNumber::normalize('fs 228 740'))->toBe('FS228740')
            ->and(ReceiptNumber::normalize('228740'))->toBe('228740');
    });

    it('accepts the separators receipts are printed with', function (string $typed) {
        expect(ReceiptNumber::isValid($typed))->toBeTrue();
    })->with(['228740', 'FS-228740', 'CBE/2018/00012', 'TT.1234', 'ደረሰኝ-12']);

    it('refuses empty, overlong and odd input', function (string $typed) {
        expect(ReceiptNumber::isValid($typed))->toBeFalse();
    })->with(['', '   ', '-228740', '/12', '22#8740', 'FS_228740', str_repeat('1', 65)]);
});
