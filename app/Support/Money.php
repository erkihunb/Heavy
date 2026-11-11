<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored as integer santim (1 ETB = 100 santim) and never handled as
 * a float (spec §4.4). This class is the only place amounts are parsed from
 * and formatted into text.
 */
final class Money
{
    /** At most nine digits of birr and, optionally, one or two of santim. */
    public const string PATTERN = '/^\d{1,9}(\.\d{1,2})?$/';

    /**
     * "1500" or "1500.5" or "1,500.50" → 150000 / 150050 / 150050.
     *
     * @throws InvalidArgumentException when the text is not an amount
     */
    public static function parse(string $amount): int
    {
        $amount = str_replace([',', ' '], '', trim($amount));

        if (preg_match(self::PATTERN, $amount) !== 1) {
            throw new InvalidArgumentException("[{$amount}] is not an amount of money.");
        }

        [$birr, $santim] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $birr * 100 + (int) str_pad($santim, 2, '0');
    }

    public static function isValid(string $amount): bool
    {
        return preg_match(self::PATTERN, str_replace([',', ' '], '', trim($amount))) === 1;
    }

    /**
     * 150050 → "1,500.50".
     */
    public static function decimal(int $cents): string
    {
        return number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * 150050 → "ETB 1,500.50".
     */
    public static function format(int $cents, string $currency = 'ETB'): string
    {
        return $currency.' '.self::decimal($cents);
    }

    /**
     * An amount as sent to the frontend.
     *
     * @return array{amount_cents: int, currency: string, formatted: string}
     */
    public static function present(int $cents, string $currency = 'ETB'): array
    {
        return [
            'amount_cents' => $cents,
            'currency' => $currency,
            'formatted' => self::format($cents, $currency),
        ];
    }
}
