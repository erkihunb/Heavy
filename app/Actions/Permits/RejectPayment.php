<?php

namespace App\Actions\Permits;

use App\Domain\Notifications\PermitNotifier;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\PaymentRejected;
use Carbon\CarbonImmutable;

class RejectPayment
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly PermitNotifier $notifier,
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
        });

        $this->notifier->send($permit, new PaymentRejected($permit, $note));

        return $payment;
    }
}
