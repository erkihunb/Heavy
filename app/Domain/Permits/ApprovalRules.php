<?php

namespace App\Domain\Permits;

use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;

/**
 * Two-step approval (spec §14, question 5): with `permits.two_step_approval`
 * on, an Approver decides only after a Reviewer has recorded a
 * recommendation, and may not be that Reviewer.
 */
final class ApprovalRules
{
    public function violation(Permit $permit, User $approver): ?PermitRuleViolation
    {
        if (! config('permits.two_step_approval')) {
            return null;
        }

        if ($permit->recommendation === null) {
            return new PermitRuleViolation('recommendation_required');
        }

        if ($permit->recommended_by === $approver->id) {
            return new PermitRuleViolation('approver_is_recommender');
        }

        return null;
    }

    /**
     * @throws PermitRuleViolation
     */
    public function ensureMayDecide(Permit $permit, User $approver): void
    {
        $violation = $this->violation($permit, $approver);

        if ($violation !== null) {
            throw $violation;
        }
    }
}
