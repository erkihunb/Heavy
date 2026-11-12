<?php

namespace App\Actions\Permits;

use App\Data\PermitData;
use App\Domain\Permits\PermitTransitions;
use App\Domain\Permits\PermitVehicles;
use App\Domain\Permits\RequestLetters;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Exceptions\PermitRuleViolation;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateDraftPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly PermitVehicles $vehicles,
        private readonly ValidityRules $validity,
        private readonly RequestLetters $letters,
    ) {}

    /**
     * Start an application for an organization. It stays a draft, invisible
     * to staff, until the applicant submits it.
     *
     * @throws PermitRuleViolation
     */
    public function handle(Organization $organization, User $applicant, PermitData $data, ?User $actor = null): Permit
    {
        $this->validity->ensureValid($data->validFrom, $data->validTo);

        $letter = $data->letter === null ? null : $this->letters->store($data->letter);

        try {
            return $this->create($organization, $applicant, $data, $actor, $letter);
        } catch (Throwable $exception) {
            // Nothing was recorded, so the scanned letter must not stay behind.
            $this->letters->discard($letter);

            throw $exception;
        }
    }

    private function create(Organization $organization, User $applicant, PermitData $data, ?User $actor, ?string $letter): Permit
    {
        return DB::transaction(function () use ($organization, $applicant, $data, $actor, $letter): Permit {
            $permit = new Permit($data->toAttributes());
            $permit->organization_id = $organization->id;
            $permit->applicant_id = $applicant->id;
            // A walk-in application belongs to the branch of the Registrar who takes it;
            // online, the applicant says which branch they apply to.
            $permit->branch_id = $actor !== null ? $actor->branch_id : $data->branchId;
            $permit->status = PermitStatus::Draft;
            $permit->save();

            if ($data->letter !== null) {
                $this->letters->attach($permit, $data->letter, $letter);
            }

            $this->vehicles->replace($permit, $data->primaryVehicleId, $data->trailerVehicleIds);
            // The history names who started it: the applicant, or the Registrar at the office.
            $this->transitions->record($permit, null, PermitStatus::Draft, $actor ?? $applicant);

            return $permit;
        });
    }
}
