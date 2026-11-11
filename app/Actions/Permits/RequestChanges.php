<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;

class RequestChanges
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * A Reviewer sends the application back to the applicant, saying what
     * has to change.
     *
     * @throws InvalidPermitTransition
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, User $reviewer, string $note): Permit
    {
        if (blank($note)) {
            throw new PermitRuleViolation('note_required');
        }

        return $this->transitions->apply($permit, PermitStatus::ChangesRequested, $reviewer, $note);
    }
}
