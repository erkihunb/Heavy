<?php

namespace App\Domain\Permits;

use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;

/**
 * The one definition of how a permit may move between statuses (spec §3).
 *
 * `active` is computed from the dates and never stored, so nothing moves a
 * permit *into* it; it is listed as a source so that a caller holding the
 * effective status of an issued permit gets the same answers as for `issued`.
 */
final class PermitStateMachine
{
    /** @var array<string, list<PermitStatus>> */
    private const array TRANSITIONS = [
        PermitStatus::Draft->value => [PermitStatus::Submitted, PermitStatus::Withdrawn],
        PermitStatus::Submitted->value => [PermitStatus::UnderReview, PermitStatus::Withdrawn],
        PermitStatus::UnderReview->value => [
            PermitStatus::ChangesRequested,
            PermitStatus::Rejected,
            PermitStatus::AwaitingPayment,
        ],
        PermitStatus::ChangesRequested->value => [PermitStatus::Submitted],
        // A receipt sent in for verification, or a payment a cashier takes and records.
        PermitStatus::AwaitingPayment->value => [PermitStatus::PaymentSubmitted, PermitStatus::PaymentConfirmed],
        PermitStatus::PaymentSubmitted->value => [PermitStatus::PaymentConfirmed, PermitStatus::AwaitingPayment],
        // An Issuer checks the paid application, then issues and prints the permit.
        PermitStatus::PaymentConfirmed->value => [PermitStatus::Issued],
        PermitStatus::Issued->value => [PermitStatus::Expired, PermitStatus::Revoked],
        PermitStatus::Active->value => [PermitStatus::Expired, PermitStatus::Revoked],
        // Terminal.
        PermitStatus::Rejected->value => [],
        PermitStatus::Expired->value => [],
        PermitStatus::Revoked->value => [],
        PermitStatus::Withdrawn->value => [],
    ];

    public function canTransition(PermitStatus $from, PermitStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @throws InvalidPermitTransition
     */
    public function assertCanTransition(PermitStatus $from, PermitStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidPermitTransition($from, $to);
        }
    }

    /**
     * @return list<PermitStatus> the statuses a permit in the given status can move to
     */
    public function targets(PermitStatus $from): array
    {
        return self::TRANSITIONS[$from->value];
    }

    public function isTerminal(PermitStatus $status): bool
    {
        return self::TRANSITIONS[$status->value] === [];
    }
}
