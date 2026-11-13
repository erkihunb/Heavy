<?php

namespace App\Actions\Permits;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Notifications\PermitNotifier;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\PaymentRejected;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class RejectPayment
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly PermitNotifier $notifier,
        private readonly AuditLog $audit,
    ) {}

    /**
     * A cashier refuses the receipt and says why. The permit waits for
     * payment again; the rejected payment stays on record and its receipt
     * number becomes usable again.
     *
     * @throws InvalidPermitTransition
     * @throws PermitRuleViolation when the payment is not the one awaiting verification
     */
    public function handle(Payment $payment, User $cashier, string $note): Payment
    {
        Gate::forUser($cashier)->authorize(Permission::VerifyPayments->value);

        /** @var Permit $permit */
        $permit = $payment->permit()->firstOrFail();

        $this->transitions->apply($permit, PermitStatus::AwaitingPayment, $cashier, $note, function () use ($payment, $cashier, $note): void {
            $payment->refresh();

            if ($payment->status !== PaymentStatus::Submitted) {
                throw new PermitRuleViolation('payment_not_pending');
            }

            $payment->status = PaymentStatus::Rejected;
            $payment->verified_by = $cashier->id;
            $payment->verified_at = CarbonImmutable::now();
            $payment->notes = trim($note);
            $payment->save();

            $this->audit->record(AuditEvent::PaymentRejected, $payment, $cashier,
                old: ['status' => PaymentStatus::Submitted->value],
                new: ['status' => PaymentStatus::Rejected->value],
                context: ['receipt_number' => $payment->receipt_number, 'amount_cents' => $payment->amount_cents],
            );
        });

        $this->notifier->send($permit, new PaymentRejected($permit, $note));

        return $payment;
    }
}
