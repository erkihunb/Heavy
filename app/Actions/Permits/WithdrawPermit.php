<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Models\Permit;
use App\Models\User;

/**
 * Delete a request registered at the office while it is a draft or returned
 * for changes. It is not erased: it becomes withdrawn, keeps its history and
 * no longer holds its vehicles for its dates.
 */
class WithdrawPermit
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * @throws InvalidPermitTransition
     */
    public function handle(Permit $permit, User $actor, ?string $note = null): Permit
    {
        return $this->transitions->apply($permit, PermitStatus::Withdrawn, $actor, $note);
    }
}
