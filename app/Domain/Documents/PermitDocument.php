<?php

namespace App\Domain\Documents;

use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use DateTimeInterface;

/**
 * The HTML of a permit's PDF (spec §8), in Amharic or in English. Its content
 * follows the Authority's paper permit: number and date, the nine numbered
 * entries (organisation, owner, permitted time, vehicle type, plate, reason,
 * validity, receipt number, issuer and signature) and the notes, plus the QR
 * code; on one A4 landscape page set in Abyssinica SIL. Fonts are embedded as
 * data URIs so the document renders the same anywhere, with no network access.
 *
 * As on the paper permit, the receipt number and the issuer's name are
 * printed; the fee, e-mail addresses and phone numbers are left out: the
 * paper travels with the driver and is shown at the roadside.
 */
final class PermitDocument
{
    private const array FONTS = [
        'abyssinica-sil-ethiopic-400-normal.woff2' => 'U+1200-139F, U+2D80-2DDF, U+AB00-AB2F, U+1E7E0-1E7FF',
        'abyssinica-sil-latin-400-normal.woff2' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, U+2212',
    ];

    /**
     * The longest reason printed in full; a longer one (up to 2000 characters
     * may be entered) is cut here and ends with "…", so it never pushes the
     * rest of the page off the sheet.
     */
    public const int REASON_MAX_CHARACTERS = 220;

    /**
     * About how many characters of a value fit on one line of the entries
     * column at the normal size; used to estimate how many lines a value takes.
     */
    private const int CHARACTERS_PER_LINE = 42;

    /**
     * A permit that is no longer in force, revoked or expired, is stamped so
     * across the page ("የተሰረዘ" / "REVOKED", "ጊዜው ያለፈ" / "EXPIRED"). When it is
     * printed again then, the token is no longer known (only its hash is
     * kept), so $token is null and the QR code's place says so too.
     *
     * The page is one fixed A4 sheet: long values set the text smaller
     * (density "compact" or "tight") so nothing falls off the bottom.
     *
     * @param  ?string  $token  the verification token for the QR code; null when it cannot be printed
     * @param  'am'|'en'  $language  the one language the whole page is printed in
     */
    public static function html(Permit $permit, ?string $token, string $language = 'am'): string
    {
        $permit->loadMissing(['vehicles.vehicleType', 'organization', 'branch', 'approver', 'payments']);

        $timezone = config('localization.display_timezone');
        $type = $permit->permit_type->value;
        $issuedAt = $permit->issued_at?->setTimezone($timezone);

        // The receipt the fee was paid with: the verified one, else the latest submitted.
        $payment = $permit->payments->firstWhere('status', PaymentStatus::Verified) ?? $permit->payments->last();

        $vehicles = $permit->vehicles->map(fn (Vehicle $vehicle): array => [
            'plate' => $vehicle->plate_normalized,
            'role' => __('permits.vehicle_roles.'.$vehicle->roleOnPermit()?->value, [], $language),
            'type' => $vehicle->typeName($language),
            'owner' => $vehicle->owner_name,
        ])->all();
        $organization = $permit->organization->localizedName($language);
        $reason = self::shorten((string) $permit->reason, self::REASON_MAX_CHARACTERS);
        $hours = $permit->movementHours()?->label($language);

        $stamp = match ($permit->status) {
            PermitStatus::Revoked => 'revoked',
            PermitStatus::Expired => 'expired',
            default => null,
        };

        return view('documents.permit', [
            'language' => $language,
            'permit' => $permit,
            'density' => self::density([
                $organization,
                $reason,
                implode(', ', array_unique(array_filter(array_column($vehicles, 'owner')))),
                implode(', ', array_unique(array_column($vehicles, 'type'))),
                implode(', ', array_map(fn (array $vehicle): string => "{$vehicle['plate']} ({$vehicle['role']})", $vehicles)),
            ], extraLines: ($hours !== null ? 1 : 0)
                + (count($vehicles) > 1 ? 1 : 0)
                // The "revoked on" / "expired on" line under the title takes about two lines.
                + ($stamp !== null ? 2 : 0)),
            'reason' => $reason,
            // The check code printed under the number and date; null when switched off.
            'certificate_code' => CertificateCode::for($permit),
            // The Authority's name in both languages, on every page whatever its language.
            'authority_am' => __('common.authority', [], 'am'),
            'authority_en' => __('common.authority', [], 'en'),
            'vehicles' => $vehicles,
            'organization' => $organization,
            'branch' => $permit->branch?->localizedName($language),
            'type' => __("permits.types.{$type}", [], $language),
            'valid_from' => self::date($permit->valid_from, $language),
            'valid_to' => self::date($permit->valid_to, $language),
            // The hours of the day the vehicles may move; null for any time of day.
            'hours' => $hours,
            'issued_at' => self::date($issuedAt, $language),
            'receipt_number' => $payment?->receipt_number,
            'issuer' => $permit->approver?->name,
            'notes' => self::notes($language),
            // "revoked" or "expired", and since when; null for a permit in force.
            'stamp' => $stamp,
            'stamp_date' => match ($stamp) {
                'revoked' => self::date($permit->revoked_at?->setTimezone($timezone), $language),
                'expired' => self::date($permit->valid_to, $language),
                default => null,
            },
            'qr' => $token === null ? null : PermitQrCode::svg($token),
            'verify_url' => rtrim((string) config('permits.verify_url'), '/').'/verify',
            'fonts' => self::fontFaces(),
            'watermark' => self::watermark(),
        ])->render();
    }

    /**
     * How tightly the page must be set for these values to fit on the sheet:
     * "normal", "compact" (one size smaller) or "tight" (two sizes smaller),
     * from the number of extra lines the values are estimated to take.
     *
     * @param  list<string>  $values
     */
    public static function density(array $values, int $extraLines = 0): string
    {
        foreach ($values as $value) {
            $extraLines += max(1, (int) ceil(mb_strlen($value) / self::CHARACTERS_PER_LINE)) - 1;
        }

        return match (true) {
            $extraLines <= 1 => 'normal',
            $extraLines <= 4 => 'compact',
            default => 'tight',
        };
    }

    /**
     * The text, cut at a word to at most $max characters and ended with "…"
     * when it was longer.
     */
    private static function shorten(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.7 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:-').'…';
    }

    /**
     * The notes (ማሳሰቢያ) printed at the foot of the page: a list in config, or
     * the text an Admin saved in System settings, one note per line.
     *
     * @return list<string>
     */
    private static function notes(string $language): array
    {
        $notes = config("permits.document.notes.{$language}", []);
        $lines = is_array($notes) ? $notes : preg_split('/\R/u', (string) $notes);

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));
    }

    /**
     * The truck drawing laid faintly behind the page, as a data URI so the
     * renderer needs no network; null if the file is missing.
     */
    private static function watermark(): ?string
    {
        $path = resource_path('images/permit-watermark.png');

        return is_file($path)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path))
            : null;
    }

    /**
     * A date as the page shows it. Amharic writes the Ethiopian date only, as
     * on the paper permit (dd/mm/yyyy); English writes the Gregorian date in
     * words with the Ethiopian one beside it in brackets.
     *
     * @return array{main: string, alt: string|null}|null
     */
    private static function date(?DateTimeInterface $date, string $language): ?array
    {
        $presented = EthiopianCalendar::present($date, $language);

        if ($date === null || $presented === null) {
            return null;
        }

        return $language === 'am'
            ? ['main' => $presented['ethiopian'], 'alt' => null]
            : ['main' => $date->format('j M Y'), 'alt' => "{$presented['ethiopian_label']} E.C."];
    }

    /**
     * @return list<array{src: string, range: string}>
     */
    private static function fontFaces(): array
    {
        $faces = [];

        foreach (self::FONTS as $file => $range) {
            $faces[] = [
                'src' => 'data:font/woff2;base64,'.base64_encode((string) file_get_contents(resource_path("fonts/{$file}"))),
                'range' => $range,
            ];
        }

        return $faces;
    }
}
