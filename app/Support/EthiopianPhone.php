<?php

namespace App\Support;

/**
 * Ethiopian phone numbers (mobile and landline), stored in international
 * format: "+251" followed by the nine-digit national number.
 */
final class EthiopianPhone
{
    /**
     * @return string|null the number as "+251XXXXXXXXX", or null when it is not an Ethiopian number
     */
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/[\s\-().]+/', '', trim($value)) ?? '';

        if (preg_match('/^(?:\+?251|0)?([1-9]\d{8})$/', $digits, $matches) !== 1) {
            return null;
        }

        return '+251'.$matches[1];
    }
}
