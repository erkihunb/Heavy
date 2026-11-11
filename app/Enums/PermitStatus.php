<?php

namespace App\Enums;

use App\Models\Permit;

/**
 * The stages of a permit (spec §3). Changes are only made by the Actions in
 * App\Actions\Permits, which ask App\Domain\Permits\PermitStateMachine first.
 *
 * `Active` is never stored: a permit stays `issued` in the database and
 * {@see Permit::effectiveStatus()} reports it as active (or
 * expired) from its dates.
 */
enum PermitStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentSubmitted = 'payment_submitted';
    case Issued = 'issued';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Withdrawn = 'withdrawn';

    /**
     * The applicant may still change the application.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    /**
     * The permit reserves its vehicles for its dates (spec §4.1).
     */
    public function blocksVehicles(): bool
    {
        return in_array($this, [self::AwaitingPayment, self::PaymentSubmitted, self::Issued, self::Active], true);
    }

    /**
     * Staff never see an application the applicant has not submitted or has taken back.
     */
    public function isVisibleToStaff(): bool
    {
        return ! in_array($this, [self::Draft, self::Withdrawn], true);
    }

    /**
     * The application is waiting for the Authority.
     */
    public function isAwaitingReview(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview], true);
    }

    /**
     * @return list<self> the statuses a row can have in the database
     */
    public static function stored(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status !== self::Active));
    }
}
