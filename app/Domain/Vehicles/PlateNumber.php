<?php

namespace App\Domain\Vehicles;

use App\Enums\PlateRegion;
use App\Exceptions\InvalidPlateNumber;
use Normalizer;
use Stringable;

/**
 * A validated, normalized vehicle plate number: <region>-<code>-<number>,
 * e.g. "አአ-03-A45301" or "ኢት-03-40134".
 *
 * `normalized` is the identity of a plate (unique in `vehicles.plate_normalized`);
 * `raw` keeps exactly what was typed for audit purposes.
 */
final readonly class PlateNumber implements Stringable
{
    /**
     * Characters treated as a separator between the three parts: every Unicode
     * dash, whitespace and the underscore (seen in the legacy sheet).
     */
    private const string SEPARATORS = '/[\p{Pd}\x{2212}\s_]+/u';

    /**
     * The separator between region and code is optional (letters → digits is
     * unambiguous); the one between code and number is required.
     */
    private const string FORMAT = '/^(?<region>\p{L}{2,4})-?(?<code>\d{1,2})-(?<number>[A-Z]?\d{4,6})$/u';

    private function __construct(
        public string $raw,
        public string $normalized,
        public PlateRegion $region,
        public string $code,
        public string $number,
    ) {}

    /**
     * @throws InvalidPlateNumber
     */
    public static function parse(string $raw): self
    {
        $value = class_exists(Normalizer::class)
            ? (Normalizer::normalize($raw, Normalizer::FORM_KC) ?: $raw)
            : $raw;

        $value = (string) preg_replace(self::SEPARATORS, '-', $value);
        $value = mb_strtoupper(trim($value, '-'));

        if (preg_match(self::FORMAT, $value, $parts) !== 1) {
            throw InvalidPlateNumber::malformed($raw);
        }

        $region = PlateRegion::fromPrefix($parts['region']);

        if ($region === null) {
            throw InvalidPlateNumber::unknownRegion($raw, $parts['region']);
        }

        $code = str_pad($parts['code'], 2, '0', STR_PAD_LEFT);

        return new self(
            raw: $raw,
            normalized: "{$region->value}-{$code}-{$parts['number']}",
            region: $region,
            code: $code,
            number: $parts['number'],
        );
    }

    public static function tryParse(string $raw): ?self
    {
        try {
            return self::parse($raw);
        } catch (InvalidPlateNumber) {
            return null;
        }
    }

    public function equals(self $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    public function __toString(): string
    {
        return $this->normalized;
    }
}
