<?php

namespace App\Actions\Permits;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Notifications\PermitNotifier;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\PermitIssued;
use Carbon\CarbonImmutable;

class VerifyPayment
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly IssuePermit $issuePermit,
        private readonly PermitNotifier $notifier,
        private readonly AuditLog $audit,
    ) {}

    /**
     * A cashier confirms that the receipt is genuine and covers the fee. The
     * permit is issued: it gets its verification token, and its document is
     * queued once the transaction commits.
     *
     * @throws InvalidPermitTransition
     * @throws PermitRuleViolation when the cashier approved this permit, or the payment is not the one awaiting verification
     */
    public function handle(Payment $payment, User $cashier, ?string $note = null): Payment
    {
        /** @var Permit $permit */
        $permit = $payment->permit()->firstOrFail();

        $this->transitions->apply($permit, PermitStatus::Issued, $cashier, $note, function (Permit $permit) use ($payment, $cashier, $note): void {
            $payment->refresh();

            if ($payment->status !== PaymentStatus::Submitted) {
                throw new PermitRuleViolation('payment_not_pending');
            }

            // Separation of duties (spec §2): approving and collecting are two people's jobs.
            if (config('permits.separation_of_duties') && $permit->approved_by === $cashier->id) {
                throw new PermitRuleViolation('verifier_is_approver');
            }

            $now = CarbonImmutable::now();

            $payment->status = PaymentStatus::Verified;
            $payment->verified_by = $cashier->id;
            $payment->verified_at = $now;
            $payment->notes = filled($note) ? trim((string) $note) : null;
            $payment->save();

            $this->audit->record(AuditEvent::PaymentVerified, $payment, $cashier,
                old: ['status' => PaymentStatus::Submitted->value],
                new: ['status' => PaymentStatus::Verified->value],
                context: ['receipt_number' => $payment->receipt_number, 'amount_cents' => $payment->amount_cents],
            );

            $permit->issued_at = $now;
            $this->issuePermit->handle($permit);
        });

        // The cashier's remark on a verified payment is internal: not passed on.
        $this->notifier->send($permit, new PermitIssued($permit));

        return $payment;
    }
}
