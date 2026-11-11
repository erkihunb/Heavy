<?php

namespace App\Actions\Permits;

use App\Data\PermitData;
use App\Domain\Permits\PermitTransitions;
use App\Domain\Permits\PermitVehicles;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Exceptions\PermitRuleViolation;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDraftPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly PermitVehicles $vehicles,
        private readonly ValidityRules $validity,
    ) {}

    /**
     * Start an application for an organization. It stays a draft, invisible
     * to staff, until the applicant submits it.
     *
     * @throws PermitRuleViolation
     */
    public function handle(Organization $organization, User $applicant, PermitData $data): Permit
    {
        $this->validity->ensureValid($data->validFrom, $data->validTo);

        return DB::transaction(function () use ($organization, $applicant, $data): Permit {
            $permit = new Permit($data->toAttributes());
            $permit->organization_id = $organization->id;
            $permit->applicant_id = $applicant->id;
            $permit->status = PermitStatus::Draft;
            $permit->save();

            $this->vehicles->replace($permit, $data->primaryVehicleId, $data->trailerVehicleIds);
            $this->transitions->record($permit, null, PermitStatus::Draft, $applicant);

            return $permit;
        });
    }
}
