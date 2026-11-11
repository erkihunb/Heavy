<?php

namespace App\Actions\Permits;

use App\Domain\Notifications\PermitNotifier;
use App\Domain\Permits\ApprovalRules;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\PermitRejected;

class RejectPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly ApprovalRules $rules,
        private readonly PermitNotifier $notifier,
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

        $rejected = $this->transitions->apply(
            $permit,
            PermitStatus::Rejected,
            $approver,
            $note,
            fn (Permit $permit) => $this->rules->ensureMayDecide($permit, $approver),
        );

        $this->notifier->send($rejected, new PermitRejected($rejected, $note));

        return $rejected;
    }
}
