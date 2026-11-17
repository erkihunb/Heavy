<?php

namespace App\Actions\Institution;

use App\Actions\Permits\IssuePermit;
use App\Domain\Institution\InstitutionFleet;
use App\Domain\Permits\OverlapGuard;
use App\Domain\Permits\PermitNumberGenerator;
use App\Domain\Permits\PermitTransitions;
use App\Domain\Permits\PermitVehicles;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Gives one of the Authority's own vehicles its permit, directly: no
 * application, review, approval or payment. The permit is in force at once,
 * numbered in the institution series, with a QR code and a paper of its own.
 * Everything else a permit does still holds: a vehicle carries one permit at
 * a time, it expires on its last day, it can be revoked, and it is checked
 * on the road like any other.
 */
final class IssueInstitutionPermit
{
    /** The longest an institution permit may run: a year. */
    public const int MAX_DAYS = 366;

    public function __construct(
        private readonly PermitNumberGenerator $numbers,
        private readonly PermitVehicles $vehicles,
        private readonly OverlapGuard $overlap,
        private readonly PermitTransitions $transitions,
        private readonly IssuePermit $issue,
    ) {}

    public function handle(User $issuer, Vehicle $vehicle, CarbonImmutable $validFrom, CarbonImmutable $validTo, string $reason, ?string $route = null): Permit
    {
        Gate::forUser($issuer)->authorize(Permission::IssueInstitutionPermits->value);

        $organization = InstitutionFleet::organization();

        if ($organization === null || $vehicle->organization_id !== $organization->id) {
            throw ValidationException::withMessages(['vehicles' => __('institution.errors.not_institution_vehicle')]);
        }

        if ($validTo->lessThan($validFrom) || $validFrom->diffInDays($validTo) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['valid_to' => __('institution.errors.period', ['max' => self::MAX_DAYS])]);
        }

        try {
            return DB::transaction(function () use ($issuer, $vehicle, $organization, $validFrom, $validTo, $reason, $route): Permit {
                $now = CarbonImmutable::now();

                $permit = new Permit([
                    'permit_type' => PermitType::FullDay,
                    'reason' => trim($reason),
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                ]);
                $permit->is_institutional = true;
                $permit->route = filled($route) ? trim((string) $route) : null;
                $permit->organization_id = $organization->id;
                // Nobody applied: the member of staff who gave it stands in every place a person is named.
                $permit->applicant_id = $issuer->id;
                $permit->approved_by = $issuer->id;
                $permit->issued_by = $issuer->id;
                $permit->submitted_at = $now;
                $permit->approved_at = $now;
                $permit->issued_at = $now;
                $permit->status = PermitStatus::Issued;
                $permit->permit_number = $this->numbers->nextInstitutional($now);
                $permit->save();

                $this->vehicles->replace($permit, $vehicle->id, []);
                $this->overlap->ensureNoOverlap($permit, lock: true);
                $this->transitions->record($permit, null, PermitStatus::Issued, $issuer, __('institution.history.issued', [], 'en'));
                $this->issue->handle($permit);

                return $permit;
            });
        } catch (PermitRuleViolation|QueryException $exception) {
            // The vehicle already holds a permit in these days (the rule, or the database's own guard).
            if ($exception instanceof QueryException && ! str_contains($exception->getMessage(), 'permit_vehicles')) {
                throw $exception;
            }

            throw ValidationException::withMessages(['vehicles' => __('institution.errors.overlap', ['plate' => $vehicle->plate_normalized])]);
        }
    }
}
