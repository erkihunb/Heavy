<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class StartReview
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * A Reviewer, or the person who decides, takes a submitted application
     * out of the queue.
     *
     * @throws InvalidPermitTransition
     */
    public function handle(Permit $permit, User $reviewer): Permit
    {
        if (! Gate::forUser($reviewer)->any([Permission::ReviewPermits->value, Permission::ApprovePermits->value])) {
            throw new AuthorizationException;
        }

        return $this->transitions->apply($permit, PermitStatus::UnderReview, $reviewer);
    }
}
