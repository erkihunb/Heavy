<?php

namespace App\Actions\Permits;

use App\Data\PaymentData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Exceptions\DuplicateReceipt;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The receipt a Registrar takes at the desk together with the request. It is
 * kept as a submitted payment and changes nothing yet: once the request is
 * approved for the same fee it goes to the cashier, who verifies it as any
 * other receipt (ApprovePermit, VerifyPayment). Until then it only reserves
 * the receipt number.
 */
class TakeReceiptAtIntake
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Permit $permit, User $registrar, PaymentData $data): Payment
    {
        if (! in_array($permit->status, [PermitStatus::Draft, PermitStatus::Submitted], true)) {
            throw new PermitRuleViolation('payment_not_pending');
        }

        $disk = Storage::disk(config('permits.documents_disk'));
        $path = null;

        if ($data->receiptFile !== null) {
            // A random name: nothing about the applicant or the file chosen reaches the disk.
            $path = $disk->putFileAs('receipts', $data->receiptFile, Str::random(40).'.'.($data->receiptFile->guessExtension() ?? 'bin'));

            if ($path === false) {
                throw new PermitRuleViolation('receipt_not_stored');
            }
        }

        try {
            return DB::transaction(function () use ($permit, $registrar, $data, $path): Payment {
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
                $payment->submitted_by = $registrar->id;
                $payment->receipt_file_path = $path;
                $payment->status = PaymentStatus::Submitted;
                $payment->save();

                $this->audit->record(AuditEvent::PaymentTakenAtIntake, $payment, $registrar,
                    new: ['status' => PaymentStatus::Submitted->value],
                    context: ['receipt_number' => $payment->receipt_number, 'amount_cents' => $payment->amount_cents, 'permit_id' => $permit->id],
                );

                return $payment;
            });
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
