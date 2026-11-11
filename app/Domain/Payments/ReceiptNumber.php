<?php

namespace App\Domain\Payments;

/**
 * Receipt numbers are compared in one canonical form, so "fs 228740",
 * "FS-228740" and "FS228740 " are the same receipt (spec §4.5).
 */
final class ReceiptNumber
{
    public const int MAX_LENGTH = 64;

    /**
     * Upper-case Latin letters, no spaces, one kind of dash, nothing else changed.
     */
    public static function normalize(string $value): string
    {
        $value = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return mb_strtoupper($value);
    }

    /**
     * Letters, digits and the separators receipts are printed with.
     */
    public static function isValid(string $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized !== ''
            && mb_strlen($normalized) <= self::MAX_LENGTH
            && preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\-\/.]*$/u', $normalized) === 1;
    }
}
