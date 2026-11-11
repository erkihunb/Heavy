<?php

namespace App\Actions\Permits;

use App\Domain\Permits\ApprovalRules;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;

class RejectPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly ApprovalRules $rules,
    ) {}

    /**
     * An Approver refuses an application under review, giving the reason.
     * This is final.
     *
     * @throws InvalidPermitTransition
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, User $approver, string $note): Permit
    {
        if (blank($note)) {
            throw new PermitRuleViolation('note_required');
        }

        return $this->transitions->apply(
            $permit,
            PermitStatus::Rejected,
            $approver,
            $note,
            fn (Permit $permit) => $this->rules->ensureMayDecide($permit, $approver),
        );
    }
}
