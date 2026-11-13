<?php

namespace App\Actions\Permits;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class VerifyPayment
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly AuditLog $audit,
    ) {}

    /**
     * A cashier confirms that a receipt sent in is genuine and covers the
     * fee. The application moves on to an Issuer, who issues and prints the
     * permit (IssuePaidPermit).
     *
     * @throws InvalidPermitTransition
     * @throws PermitRuleViolation when the cashier approved this permit, or the payment is not the one awaiting verification
     */
    public function handle(Payment $payment, User $cashier, ?string $note = null): Payment
    {
        Gate::forUser($cashier)->authorize(Permission::VerifyPayments->value);

        /** @var Permit $permit */
        $permit = $payment->permit()->firstOrFail();

        $this->transitions->apply($permit, PermitStatus::PaymentConfirmed, $cashier, $note, function (Permit $permit) use ($payment, $cashier, $note): void {
            $payment->refresh();

            if ($payment->status !== PaymentStatus::Submitted) {
                throw new PermitRuleViolation('payment_not_pending');
            }

            // Separation of duties (spec §2): approving and collecting are two people's jobs.
            if (config('permits.separation_of_duties') && $permit->approved_by === $cashier->id) {
                throw new PermitRuleViolation('verifier_is_approver');
            }

            $payment->status = PaymentStatus::Verified;
            $payment->verified_by = $cashier->id;
            $payment->verified_at = CarbonImmutable::now();
            $payment->notes = filled($note) ? trim((string) $note) : null;
            $payment->save();

            $this->audit->record(AuditEvent::PaymentVerified, $payment, $cashier,
                old: ['status' => PaymentStatus::Submitted->value],
                new: ['status' => PaymentStatus::Verified->value],
                context: ['receipt_number' => $payment->receipt_number, 'amount_cents' => $payment->amount_cents],
            );
        });

        return $payment;
    }
}
