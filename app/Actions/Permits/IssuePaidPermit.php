<?php

namespace App\Actions\Permits;

use App\Domain\Notifications\PermitNotifier;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\PermitIssued;
use Carbon\CarbonImmutable;

class IssuePaidPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly IssuePermit $issuePermit,
        private readonly PermitNotifier $notifier,
    ) {}

    /**
     * An Issuer has checked a paid application and issues the permit: it
     * gets its verification token, and its document is queued for printing
     * once the transaction commits.
     *
     * @throws InvalidPermitTransition when the payment has not been confirmed
     */
    public function handle(Permit $permit, User $issuer, ?string $note = null): Permit
    {
        $issued = $this->transitions->apply($permit, PermitStatus::Issued, $issuer, $note, function (Permit $permit) use ($issuer): void {
            $permit->issued_at = CarbonImmutable::now();
            $permit->issued_by = $issuer->id;
            $this->issuePermit->handle($permit);
        });

        $this->notifier->send($issued, new PermitIssued($issued));

        return $issued;
    }
}
