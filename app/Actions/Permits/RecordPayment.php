<?php

namespace App\Actions\Permits;

use App\Data\PaymentData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Exceptions\DuplicateReceipt;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RecordPayment
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly AuditLog $audit,
    ) {}

    /**
     * A cashier takes the fee at the office, or checks that it was paid by
     * bank or another method, and records the receipt. The payment is
     * verified at once and the application moves on to an Issuer.
     *
     * @throws InvalidPermitTransition
     * @throws DuplicateReceipt
     * @throws PermitRuleViolation when the amount is not the fee, or the cashier approved this permit
     */
    public function handle(Permit $permit, User $cashier, PaymentData $data, ?string $note = null): Payment
    {
        Gate::forUser($cashier)->authorize(Permission::VerifyPayments->value);

        $disk = Storage::disk(config('permits.documents_disk'));
        $path = null;

        if ($data->receiptFile !== null) {
            $extension = $data->receiptFile->guessExtension() ?? 'bin';
            $path = $disk->putFileAs('receipts', $data->receiptFile, Str::random(40).'.'.$extension);

            if ($path === false) {
                throw new PermitRuleViolation('receipt_not_stored');
            }
        }

        try {
            $payment = null;

            $this->transitions->apply($permit, PermitStatus::PaymentConfirmed, $cashier, $note, function (Permit $permit) use ($cashier, $data, $path, $note, &$payment): void {
                if ($permit->fee_cents === null || $data->amountCents !== $permit->fee_cents) {
                    throw new PermitRuleViolation('amount_mismatch');
                }

                // Separation of duties (spec §2): approving and collecting are two people's jobs.
                if (config('permits.separation_of_duties') && $permit->approved_by === $cashier->id) {
                    throw new PermitRuleViolation('verifier_is_approver');
                }

                if (Payment::query()
                    ->where('receipt_number', $data->receiptNumber)
                    ->where('status', '!=', PaymentStatus::Rejected)
                    ->exists()
                ) {
                    throw new DuplicateReceipt($data->receiptNumber);
                }

                $payment = new Payment([
                    'method' => $data->method,
                    'receipt_number' => $data->receiptNumber,
                    'amount_cents' => $data->amountCents,
                    'paid_at' => $data->paidAt->toDateString(),
                ]);
                $payment->permit_id = $permit->id;
                $payment->submitted_by = $cashier->id;
                $payment->receipt_file_path = $path;
                $payment->status = PaymentStatus::Verified;
                $payment->verified_by = $cashier->id;
                $payment->verified_at = CarbonImmutable::now();
                $payment->notes = filled($note) ? trim((string) $note) : null;
                $payment->save();

                $this->audit->record(AuditEvent::PaymentRecorded, $payment, $cashier,
                    new: ['status' => PaymentStatus::Verified->value],
                    context: [
                        'receipt_number' => $payment->receipt_number,
                        'amount_cents' => $payment->amount_cents,
                        'method' => $data->method->value,
                    ],
                );
            });

            /** @var Payment $payment */
            return $payment;
        } catch (Throwable $exception) {
            // Nothing was recorded, so the uploaded file must not stay behind.
            if ($path !== null) {
                $disk->delete($path);
            }

            if ($exception instanceof UniqueConstraintViolationException) {
                throw new DuplicateReceipt($data->receiptNumber);
            }

            throw $exception;
        }
    }
}
