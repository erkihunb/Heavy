<?php

namespace App\Domain\Legacy;

use App\Domain\Payments\ReceiptNumber;
use App\Enums\PermitType;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Normalizer;
use Throwable;

/**
 * Reads the free-text values of the legacy Google Form (spec §13). Every
 * method either returns a clean value or throws InvalidLegacyValue with a
 * reason a person can act on. Pure: no database access.
 */
final class LegacyValues
{
    /** Ethiopian years a legacy permit can plausibly be in. */
    private const int MIN_YEAR = 2000;

    private const int MAX_YEAR = 2100;

    /**
     * Text reduced for comparison: Unicode-normalised, without spaces,
     * underscores or punctuation, Latin letters in lower case.
     * "ሙሉ ቀን_____" and " ሙሉ ቀን/" both become "ሙሉቀን".
     */
    public static function normalizeText(string $value): string
    {
        $value = class_exists(Normalizer::class) ? (Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value) : $value;
        $value = (string) preg_replace('/[\s\p{P}\p{S}_]+/u', '', $value);

        return mb_strtolower($value);
    }

    /**
     * "ሙሉ ቀን" and its many spellings → full day; "ይለፍ" and its spellings → pass.
     *
     * @throws InvalidLegacyValue
     */
    public static function permitType(string $raw): PermitType
    {
        $text = self::normalizeText($raw);

        return match (true) {
            str_starts_with($text, 'ሙሉቀ'), str_contains($text, 'ሙሉቀን'), $text === 'fullday' => PermitType::FullDay,
            str_contains($text, 'ይለፍ'), $text === 'pass' => PermitType::Pass,
            default => throw new InvalidLegacyValue($text === '' ? 'Permit type is empty.' : 'Permit type is not "ሙሉ ቀን" or "ይለፍ".'),
        };
    }

    /**
     * The vehicle type code for a spelling in the reviewed mapping file, or null.
     *
     * @param  array<string, string>  $map  database/import/vehicle_type_map.php
     */
    public static function vehicleTypeCode(string $raw, array $map): ?string
    {
        static $normalizedMaps = [];
        $key = md5(serialize($map));
        $normalizedMaps[$key] ??= collect($map)->mapWithKeys(fn (string $code, string $spelling): array => [self::normalizeText($spelling) => $code])->all();

        return $normalizedMaps[$key][self::normalizeText($raw)] ?? null;
    }

    /**
     * Plates as typed, split where the sheet listed a tractor and its trailer
     * together: "ኢት-03-A33490/ ኢት-03-40134" → the primary, then the trailers.
     *
     * @return list<string>
     *
     * @throws InvalidLegacyValue when there is no plate at all
     */
    public static function plates(string $raw): array
    {
        $parts = array_values(array_filter(
            array_map('trim', preg_split('#[/\\\\,;]+#u', $raw) ?: []),
            fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            throw new InvalidLegacyValue('Plate number is empty.');
        }

        return $parts;
    }

    /**
     * "DD/MM/YYYY አስከ|እስከ DD/MM/YYYY" in the Ethiopian calendar → the first
     * and last Gregorian day (midnight UTC, as all permit dates).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws InvalidLegacyValue
     */
    public static function validity(string $raw): array
    {
        preg_match_all('/(\d{1,2})\s*[\/.\-]\s*(\d{1,2})\s*[\/.\-]\s*(\d{4})/u', $raw, $dates, PREG_SET_ORDER);

        if (count($dates) !== 2) {
            throw new InvalidLegacyValue(trim($raw) === ''
                ? 'Validity period is empty.'
                : 'Validity period does not contain two dates "DD/MM/YYYY እስከ DD/MM/YYYY".');
        }

        [$from, $to] = array_map(function (array $date): CarbonImmutable {
            [, $day, $month, $year] = array_map('intval', $date);

            if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
                throw new InvalidLegacyValue("Year {$year} is not a plausible Ethiopian year.");
            }

            try {
                return EthiopianCalendar::toGregorian($year, $month, $day);
            } catch (InvalidArgumentException) {
                throw new InvalidLegacyValue(sprintf('%02d/%02d/%d is not a date in the Ethiopian calendar.', $day, $month, $year));
            }
        }, $dates);

        if ($from->greaterThan($to)) {
            throw new InvalidLegacyValue('The first day of the validity period is after the last day.');
        }

        return [$from, $to];
    }

    /**
     * "1000", "1,000", "6000 ብር", "ETB 15,000.00" → santim; null when empty.
     *
     * @throws InvalidLegacyValue when something is written that is not an amount
     */
    public static function amountCents(string $raw): ?int
    {
        $value = trim((string) preg_replace('/(ETB|birr|ብር)/iu', '', $raw));

        if ($value === '') {
            return null;
        }

        if (! Money::isValid($value)) {
            throw new InvalidLegacyValue('Amount paid is not a number of birr.');
        }

        return Money::parse($value);
    }

    /**
     * The receipt number in canonical form, or null when the cell is empty.
     *
     * @throws InvalidLegacyValue when the cell holds something that is not a receipt number
     */
    public static function receipt(string $raw): ?string
    {
        if (trim($raw) === '') {
            return null;
        }

        if (! ReceiptNumber::isValid($raw)) {
            throw new InvalidLegacyValue('Receipt number has characters a receipt number cannot have.');
        }

        return ReceiptNumber::normalize($raw);
    }

    /**
     * The form's submission time, written in Addis Ababa time, as UTC.
     *
     * @param  list<string>  $formats  PHP date formats to try, in order
     *
     * @throws InvalidLegacyValue
     */
    public static function timestamp(string $raw, array $formats): CarbonImmutable
    {
        $raw = trim($raw);
        $timezone = (string) config('localization.display_timezone');

        foreach ($formats as $format) {
            try {
                $moment = CarbonImmutable::createFromFormat("!{$format}", $raw, $timezone);
            } catch (Throwable) {
                continue;
            }

            if ($moment !== null && $moment->format($format) === $raw) {
                return $moment->utc();
            }
        }

        throw new InvalidLegacyValue($raw === '' ? 'Timestamp is empty.' : 'Timestamp is not in a known format.');
    }

    /**
     * The row's identity for idempotent re-runs (spec §13): a hash of the
     * timestamp, the e-mail address and the plate cell, as written.
     */
    public static function sourceRef(string $timestamp, string $email, string $plates): string
    {
        return hash('sha256', trim($timestamp).'|'.mb_strtolower(trim($email)).'|'.trim($plates));
    }

    /**
     * An organization name as displayed (single spaces, trimmed) and the key
     * used to recognise the same organization in other rows.
     *
     * @return array{name: string, key: string}
     */
    public static function organization(string $raw): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $raw), " \t\n\r\0\x0B.,;:-_/");

        return ['name' => $name, 'key' => self::normalizeText($name)];
    }
}
