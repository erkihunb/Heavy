<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;

class RevokePermit
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * An Approver cancels an issued permit, giving the reason. It takes
     * effect at once (spec §4.8) and frees the vehicles for other permits.
     *
     * @throws InvalidPermitTransition
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, User $approver, string $reason): Permit
    {
        if (blank($reason)) {
            throw new PermitRuleViolation('revocation_reason_required');
        }

        return $this->transitions->apply($permit, PermitStatus::Revoked, $approver, $reason, function (Permit $permit) use ($approver, $reason): void {
            $permit->revoked_by = $approver->id;
            $permit->revoked_at = CarbonImmutable::now();
            $permit->revocation_reason = trim($reason);
        });
    }
}
