<?php

namespace App\Actions\Permits;

use App\Data\PermitData;
use App\Domain\Permits\PermitTransitions;
use App\Domain\Permits\PermitVehicles;
use App\Domain\Permits\RequestLetters;
use App\Domain\Permits\ValidityRules;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateDraftPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly PermitVehicles $vehicles,
        private readonly ValidityRules $validity,
        private readonly RequestLetters $letters,
    ) {}

    /**
     * Change an application that is still a draft, or one the Authority sent
     * back for changes. The status does not change.
     *
     * @param  bool  $keepBranch  true when a Registrar edits it: the application stays at its branch
     *
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, PermitData $data, bool $keepBranch = false): Permit
    {
        $this->validity->ensureValid($data->validFrom, $data->validTo);

        $letter = $data->letter === null ? null : $this->letters->store($data->letter);

        try {
            return $this->update($permit, $data, $keepBranch, $letter);
        } catch (Throwable $exception) {
            $this->letters->discard($letter);

            throw $exception;
        }
    }

    private function update(Permit $permit, PermitData $data, bool $keepBranch, ?string $letter): Permit
    {
        return DB::transaction(function () use ($permit, $data, $keepBranch, $letter): Permit {
            $this->transitions->lock($permit);

            if (! $permit->status->isEditable()) {
                throw new PermitRuleViolation('not_editable');
            }

            if (! $keepBranch) {
                $permit->branch_id = $data->branchId;
            }

            $permit->update($data->toAttributes());

            if ($data->letter !== null) {
                $this->letters->attach($permit, $data->letter, $letter);
            }
            $this->vehicles->replace($permit, $data->primaryVehicleId, $data->trailerVehicleIds);

            return $permit;
        });
    }
}
