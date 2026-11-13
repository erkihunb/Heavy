<?php

namespace App\Actions\Permits;

use App\Domain\Notifications\PermitNotifier;
use App\Domain\Permits\PermitTransitions;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\ChangesRequested;
use Illuminate\Support\Facades\Gate;

class RequestChanges
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly PermitNotifier $notifier,
    ) {}

    /**
     * A Reviewer sends the application back to the applicant, saying what
     * has to change.
     *
     * @throws InvalidPermitTransition
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, User $reviewer, string $note): Permit
    {
        Gate::forUser($reviewer)->authorize(Permission::ReviewPermits->value);

        if (blank($note)) {
            throw new PermitRuleViolation('note_required');
        }

        $returned = $this->transitions->apply($permit, PermitStatus::ChangesRequested, $reviewer, $note);

        $this->notifier->send($returned, new ChangesRequested($returned, $note));

        return $returned;
    }
}
