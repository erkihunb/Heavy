<?php

namespace App\Domain\Permits;

use App\Domain\Vehicles\PlateNumber;
use App\Enums\PermitStatus;
use App\Enums\ScanResult;
use App\Exceptions\InvalidPlateNumber;
use App\Models\Permit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds a permit for a verification and decides what it says. The answer
 * always comes from Permit::effectiveStatus(), so revocation and expiry take
 * effect the moment they happen, whatever the scheduled jobs have done.
 */
final class PermitVerifier
{
    /** Statuses a verification can find; earlier stages are "not found". */
    private const array FINDABLE = [PermitStatus::Issued, PermitStatus::Expired, PermitStatus::Revoked];

    public function __construct(private readonly VerificationTokenService $tokens) {}

    public function byToken(string $token): ?Permit
    {
        $permit = $this->tokens->find($token);

        return $permit !== null && in_array($permit->status, self::FINDABLE, true) ? $permit : null;
    }

    /**
     * A lookup needs the permit number and one of its plates, so permit
     * numbers cannot be enumerated (spec §7).
     */
    public function byNumberAndPlate(string $permitNumber, string $plate): ?Permit
    {
        try {
            $normalizedPlate = PlateNumber::parse($plate)->normalized;
        } catch (InvalidPlateNumber) {
            return null;
        }

        return Permit::query()
            ->where('permit_number', mb_strtoupper(trim($permitNumber)))
            ->whereIn('status', self::FINDABLE)
            ->whereHas('vehicles', fn (Builder $query) => $query->where('plate_normalized', $normalizedPlate))
            ->first();
    }

    /**
     * A permit in force today still lets its vehicles move only in its hours:
     * outside them the answer is "not allowed at this time".
     */
    public function result(?Permit $permit, ?CarbonImmutable $at = null): ScanResult
    {
        if ($permit === null) {
            return ScanResult::NotFound;
        }

        $at ??= CarbonImmutable::now();
        $result = ScanResult::forStatus($permit->effectiveStatus($at));
        $hours = $permit->movementHours();

        return $result === ScanResult::Valid && $hours !== null && ! $hours->allows($at)
            ? ScanResult::OutsideHours
            : $result;
    }
}
