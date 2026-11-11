<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;

class VerifyPayment
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * A cashier confirms that the receipt is genuine and covers the fee. The
     * permit is issued; from Milestone 5 this is also where the verification
     * token and the permit document are produced.
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

            $permit->issued_at = $now;
        });

        return $payment;
    }
}
