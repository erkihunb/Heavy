<?php

namespace App\Domain\Documents;

use App\Models\Permit;
use App\Models\Vehicle;

/**
 * The certificate code printed on a permit's PDF, e.g. CRT-7K3P-QX2M: an
 * HMAC (with the app key) of what the permit says, so it cannot be worked out
 * without the key and a printout whose number, type, dates, organisation or
 * plates were altered no longer matches the code shown on verification.
 *
 * The rule is an Admin setting (System settings → Code rules → Certificate code):
 * on or off, a prefix, and how many characters (printed in groups of four).
 * Characters come from Crockford's base 32, which leaves out I, L, O and U so
 * a code read aloud or typed from paper is not confused with 1, 0 or V.
 */
final class CertificateCode
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * The code for the permit under the current rule; null when the Admin has
     * switched certificate codes off.
     */
    public static function for(Permit $permit): ?string
    {
        if (! config('permits.certificate_code.enabled')) {
            return null;
        }

        $permit->loadMissing('vehicles');

        $plates = $permit->vehicles
            ->map(fn (Vehicle $vehicle): string => (string) $vehicle->plate_normalized)
            ->sort()
            ->implode(',');

        $details = implode('|', [
            $permit->permit_number,
            $permit->permit_type->value,
            $permit->valid_from->toDateString(),
            $permit->valid_to->toDateString(),
            $permit->organization_id,
            $plates,
        ]);

        $length = max(6, min(16, (int) config('permits.certificate_code.length')));
        $characters = self::base32(hash_hmac('sha256', 'certificate-code|'.$details, (string) config('app.key'), true), $length);

        $prefix = strtoupper(trim((string) config('permits.certificate_code.prefix')));
        $groups = implode('-', str_split($characters, 4));

        return $prefix === '' ? $groups : "{$prefix}-{$groups}";
    }

    /**
     * The first $length characters of the bytes in Crockford's base 32.
     */
    private static function base32(string $bytes, int $length): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $characters = '';

        foreach (str_split(substr($bits, 0, $length * 5), 5) as $chunk) {
            $characters .= self::ALPHABET[bindec($chunk)];
        }

        return $characters;
    }
}
