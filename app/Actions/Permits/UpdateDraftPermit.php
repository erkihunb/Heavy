<?php

namespace App\Actions\Permits;

use App\Data\PermitData;
use App\Domain\Permits\PermitTransitions;
use App\Domain\Permits\PermitVehicles;
use App\Domain\Permits\ValidityRules;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use Illuminate\Support\Facades\DB;

class UpdateDraftPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly PermitVehicles $vehicles,
        private readonly ValidityRules $validity,
    ) {}

    /**
     * Change an application that is still a draft, or one the Authority sent
     * back for changes. The status does not change.
     *
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, PermitData $data): Permit
    {
        $this->validity->ensureValid($data->validFrom, $data->validTo);

        return DB::transaction(function () use ($permit, $data): Permit {
            $this->transitions->lock($permit);

            if (! $permit->status->isEditable()) {
                throw new PermitRuleViolation('not_editable');
            }

            $permit->update($data->toAttributes());
            $this->vehicles->replace($permit, $data->primaryVehicleId, $data->trailerVehicleIds);

            return $permit;
        });
    }
}
