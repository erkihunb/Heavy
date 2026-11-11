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
use Carbon\CarbonImmutable;

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
