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
    // Paid and confirmed by a cashier; waiting for an Issuer to issue and print it.
    case PaymentConfirmed = 'payment_confirmed';
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
        return in_array($this, [self::AwaitingPayment, self::PaymentSubmitted, self::PaymentConfirmed, self::Issued, self::Active], true);
    }

    /**
     * Staff never see an application the applicant has not submitted or has taken back.
     */
    public function isVisibleToStaff(): bool
    {
        return ! in_array($this, self::hiddenFromStaff(), true);
    }

    /**
     * The statuses isVisibleToStaff() leaves out, for query filters.
     *
     * @return list<self>
     */
    public static function hiddenFromStaff(): array
    {
        return [self::Draft, self::Withdrawn];
    }

    /**
     * The application is waiting for the Authority.
     */
    public function isAwaitingReview(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview], true);
    }

    /**
     * The seven groups people filter by, each named by its first status: a
     * simpler view of the stages (owner's decision, 2026-11-19). Mirrored in
     * resources/js/lib/status-groups.ts.
     *
     * @return array<string, list<self>>
     */
    public static function groups(): array
    {
        return [
            self::Submitted->value => [self::Submitted, self::UnderReview],
            self::ChangesRequested->value => [self::ChangesRequested],
            self::AwaitingPayment->value => [self::AwaitingPayment, self::PaymentSubmitted],
            self::PaymentConfirmed->value => [self::PaymentConfirmed],
            self::Active->value => [self::Issued, self::Active],
            self::Rejected->value => [self::Rejected],
            self::Expired->value => [self::Expired, self::Revoked],
        ];
    }

    /**
     * The statuses a filter on this one finds: its group's, or itself alone.
     *
     * @return list<self>
     */
    public function members(): array
    {
        return self::groups()[$this->value] ?? [$this];
    }

    /**
     * @return list<self> the statuses a row can have in the database
     */
    public static function stored(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status !== self::Active));
    }
}
