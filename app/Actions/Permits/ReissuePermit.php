<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReissuePermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly IssuePermit $issuePermit,
    ) {}

    /**
     * Replace the QR code and document of an issued permit, e.g. when the
     * paper was lost (spec §11). The old QR code stops working at once. The
     * status does not change; the history records who did it and why.
     *
     * @throws PermitRuleViolation when the permit is not issued, or no reason is given
     */
    public function handle(Permit $permit, User $actor, string $reason): Permit
    {
        Gate::forUser($actor)->authorize(Permission::RevokePermits->value);

        if (blank($reason)) {
            throw new PermitRuleViolation('reissue_reason_required');
        }

        return DB::transaction(function () use ($permit, $actor, $reason): Permit {
            $this->transitions->lock($permit);

            // Expired and revoked permits stay as they are; only a live permit is reissued.
            if (! in_array($permit->effectiveStatus(CarbonImmutable::now()), [PermitStatus::Issued, PermitStatus::Active], true)) {
                throw new PermitRuleViolation('not_reissuable');
            }

            $this->issuePermit->handle($permit);
            $this->transitions->record($permit, PermitStatus::Issued, PermitStatus::Issued, $actor, trim($reason));

            return $permit;
        });
    }
}
