<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Models\Permit;
use App\Models\User;

class StartReview
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * A Reviewer takes a submitted application out of the queue.
     *
     * @throws InvalidPermitTransition
     */
    public function handle(Permit $permit, User $reviewer): Permit
    {
        return $this->transitions->apply($permit, PermitStatus::UnderReview, $reviewer);
    }
}
