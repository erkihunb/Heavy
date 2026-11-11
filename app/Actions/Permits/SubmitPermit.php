<?php

namespace App\Actions\Permits;

use App\Domain\Permits\OverlapGuard;
use App\Domain\Permits\PermitTransitions;
use App\Domain\Permits\PermitVehicles;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\OverlappingPermit;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SubmitPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly PermitVehicles $vehicles,
        private readonly ValidityRules $validity,
        private readonly OverlapGuard $overlap,
    ) {}

    /**
     * Hand a draft — or an application that was sent back for changes — to
     * the Authority for review.
     *
     * @throws InvalidPermitTransition
     * @throws OverlappingPermit
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, User $applicant): Permit
    {
        return $this->transitions->apply($permit, PermitStatus::Submitted, $applicant, changes: function (Permit $permit): void {
            // From now on the vehicles' plates are fixed. Locking their rows (in id order,
            // so two submissions cannot deadlock) waits for a plate change in progress
            // (UpdateVehicle locks the same row) and makes the next one see this submission.
            Vehicle::query()
                ->whereIn('id', DB::table('permit_vehicles')->where('permit_id', $permit->id)->select('vehicle_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            $this->validity->ensureValid($permit->valid_from, $permit->valid_to);
            $this->vehicles->ensureComplete($permit);

            // Early feedback only; the check that counts is made at approval.
            $this->overlap->ensureNoOverlap($permit);

            $permit->submitted_at = CarbonImmutable::now();

            // A resubmitted application is reviewed afresh.
            $permit->recommendation = null;
            $permit->recommended_by = null;
            $permit->recommended_at = null;
            $permit->recommendation_note = null;
        });
    }
}
