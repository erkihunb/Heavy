<?php

namespace App\Domain\Permits;

use App\Models\Permit;

/**
 * The secret behind a permit's QR code (spec §7): 32 random bytes, base64url
 * encoded (43 characters). Only its SHA-256 is stored, so a database leak does
 * not reveal working QR codes; a lookup hashes the presented token.
 */
final class VerificationTokenService
{
    public const string PATTERN = '/^[A-Za-z0-9_-]{43}$/';

    /**
     * @return array{token: string, hash: string}
     */
    public function generate(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return ['token' => $token, 'hash' => self::hash($token)];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function isWellFormed(string $token): bool
    {
        return preg_match(self::PATTERN, $token) === 1;
    }

    /**
     * The permit the token belongs to, or null. A rotated token matches nothing.
     */
    public function find(string $token): ?Permit
    {
        if (! self::isWellFormed($token)) {
            return null;
        }

        return Permit::query()->where('verification_token_hash', self::hash($token))->first();
    }

    /**
     * The address the QR code points to.
     */
    public static function url(string $token): string
    {
        return rtrim((string) config('permits.verify_url'), '/').'/v/'.$token;
    }
}
