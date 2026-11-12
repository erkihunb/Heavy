<?php

namespace App\Actions\Permits;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Notifications\PermitNotifier;
use App\Domain\Permits\ApprovalRules;
use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\OverlapGuard;
use App\Domain\Permits\PermitNumberGenerator;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\OverlappingPermit;
use App\Exceptions\PermitRuleViolation;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\PermitApproved;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class ApprovePermit
{
    /** The message raised by the overlap triggers on permit_vehicles. */
    private const string OVERLAP_SIGNAL = 'permit_vehicles_no_overlap';

    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly ApprovalRules $rules,
        private readonly OverlapGuard $overlap,
        private readonly PermitNumberGenerator $numbers,
        private readonly FeeCalculator $fees,
        private readonly PermitNotifier $notifier,
        private readonly AuditLog $audit,
    ) {}

    /**
     * An Approver accepts an application under review. The permit gets its
     * number, starts reserving its vehicles for its dates and waits for
     * payment. The fee is copied onto the permit from the schedule in force
     * today, so later changes to the schedule never change what is owed.
     *
     * @throws InvalidPermitTransition
     * @throws OverlappingPermit
     * @throws PermitRuleViolation when the approval rules refuse, or the fee schedule has no line for this permit
     */
    public function handle(Permit $permit, User $approver, ?string $note = null): Permit
    {
        $approved = $this->approve($permit, $approver, $note);

        // The organization is told what to pay (queued, after commit).
        $this->notifier->send($approved, new PermitApproved($approved, $note));

        return $this->receiptTakenAtIntake($approved, $approver);
    }

    /**
     * A receipt the Registrar took with the request (TakeReceiptAtIntake) goes
     * to the cashier now, when it is for the fee just fixed: the permit waits
     * for its verification instead of for a payment. A receipt for another
     * amount cannot pay this fee; it is set aside, with the reason, and the
     * permit waits for payment as usual.
     */
    private function receiptTakenAtIntake(Permit $permit, User $approver): Permit
    {
        /** @var Payment|null $payment */
        $payment = $permit->payments()->where('status', PaymentStatus::Submitted)->latest('created_at')->first();

        if ($payment === null) {
            return $permit;
        }

        if ($payment->amount_cents === $permit->fee_cents) {
            return $this->transitions->apply($permit, PermitStatus::PaymentSubmitted, $approver, __('payments.intake.forwarded'));
        }

        $payment->status = PaymentStatus::Rejected;
        $payment->verified_by = $approver->id;
        $payment->verified_at = CarbonImmutable::now();
        $payment->notes = __('payments.intake.amount_differs', [
            'paid' => Money::format($payment->amount_cents, $permit->currency),
            'fee' => Money::format((int) $permit->fee_cents, $permit->currency),
        ]);
        $payment->save();

        $this->audit->record(AuditEvent::PaymentRejected, $payment, $approver,
            old: ['status' => PaymentStatus::Submitted->value],
            new: ['status' => PaymentStatus::Rejected->value],
            context: ['receipt_number' => $payment->receipt_number, 'amount_cents' => $payment->amount_cents, 'reason' => 'amount_differs'],
        );

        return $permit;
    }

    /**
     * @throws InvalidPermitTransition
     * @throws OverlappingPermit
     * @throws PermitRuleViolation
     */
    private function approve(Permit $permit, User $approver, ?string $note): Permit
    {
        try {
            return $this->transitions->apply($permit, PermitStatus::AwaitingPayment, $approver, $note, function (Permit $permit) use ($approver): void {
                $this->rules->ensureMayDecide($permit, $approver);

                // Authoritative: the vehicle rows stay locked until the approval commits.
                $this->overlap->ensureNoOverlap($permit, lock: true);

                $now = CarbonImmutable::now();

                $permit->fee_cents = $this->fees->requireAmountForPermit(
                    $permit,
                    $now->setTimezone(config('localization.display_timezone')),
                );

                // The number carries the branch the application was made at.
                $permit->permit_number = $this->numbers->next($now, $permit->branch_id === null ? null : Branch::query()->find($permit->branch_id), $permit->permit_type);
                $permit->approved_by = $approver->id;
                $permit->approved_at = $now;
            });
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), self::OVERLAP_SIGNAL)) {
                throw $exception;
            }

            // The database refused an overlap the guard did not see. The
            // approval was rolled back; say which vehicle if it can be found.
            $permit->refresh();
            $this->overlap->ensureNoOverlap($permit);

            throw new OverlappingPermit;
        }
    }
}
