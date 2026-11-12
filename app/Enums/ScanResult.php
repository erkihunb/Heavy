<?php

namespace App\Enums;

/**
 * What a verification tells the person checking a permit (spec §7). Only
 * permits that have been issued can be found; anything else is "not found",
 * so applications in progress are never revealed.
 */
enum ScanResult: string
{
    case Valid = 'valid';
    /** In force today, but not at this hour: the vehicle may not move now. */
    case OutsideHours = 'outside_hours';
    case NotYetValid = 'not_yet_valid';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case NotFound = 'not_found';

    /**
     * The result for a permit in the given effective status.
     */
    public static function forStatus(PermitStatus $status): self
    {
        return match ($status) {
            PermitStatus::Active => self::Valid,
            PermitStatus::Issued => self::NotYetValid,
            PermitStatus::Expired => self::Expired,
            PermitStatus::Revoked => self::Revoked,
            default => self::NotFound,
        };
    }

    /**
     * The vehicle may move under this permit right now.
     */
    public function allowsMovement(): bool
    {
        return $this === self::Valid;
    }
}
