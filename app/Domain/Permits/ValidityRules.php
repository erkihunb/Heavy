<?php

namespace App\Domain\Permits;

use App\Exceptions\PermitRuleViolation;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The limits on the period a permit may cover (spec §4.6). Used by the form
 * validation and again by the SubmitPermit action, which is the authority.
 */
final class ValidityRules
{
    public static function maxDurationDays(): int
    {
        return (int) config('permits.max_duration_days');
    }

    /**
     * Today's calendar date in the display timezone, as midnight UTC so it
     * compares directly with stored dates.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            CarbonImmutable::now(config('localization.display_timezone'))->toDateString(),
            'UTC',
        );
    }

    /**
     * The rule a period breaks, or null when it is acceptable.
     */
    public function violation(DateTimeInterface $validFrom, DateTimeInterface $validTo): ?PermitRuleViolation
    {
        $from = $validFrom->format('Y-m-d');
        $to = $validTo->format('Y-m-d');

        if ($from > $to) {
            return new PermitRuleViolation('valid_to_before_from');
        }

        if ($from < self::today()->toDateString()) {
            return new PermitRuleViolation('valid_from_in_past');
        }

        $days = (int) CarbonImmutable::parse($from, 'UTC')->diffInDays(CarbonImmutable::parse($to, 'UTC')) + 1;

        if ($days > self::maxDurationDays()) {
            return new PermitRuleViolation('duration_too_long', ['max' => self::maxDurationDays()]);
        }

        return null;
    }

    /**
     * @throws PermitRuleViolation
     */
    public function ensureValid(DateTimeInterface $validFrom, DateTimeInterface $validTo): void
    {
        $violation = $this->violation($validFrom, $validTo);

        if ($violation !== null) {
            throw $violation;
        }
    }
}
