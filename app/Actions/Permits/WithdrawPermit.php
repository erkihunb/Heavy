<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Models\Permit;
use App\Models\User;

class WithdrawPermit
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * The applicant takes back a draft, or an application nobody has started
     * reviewing yet. This is final.
     *
     * @throws InvalidPermitTransition
     */
    public function handle(Permit $permit, User $applicant): Permit
    {
        return $this->transitions->apply($permit, PermitStatus::Withdrawn, $applicant);
    }
}
