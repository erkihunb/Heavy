<?php

namespace App\Actions\Permits;

use App\Data\PaymentData;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Exceptions\DuplicateReceipt;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SubmitPayment
{
    /** PostgreSQL SQLSTATE for a violated unique constraint. */
    private const string UNIQUE_VIOLATION = '23505';

    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * The applicant reports the fee as paid: the receipt is recorded and the
     * permit waits for a cashier to verify it.
     *
     * @throws InvalidPermitTransition
     * @throws DuplicateReceipt
     * @throws PermitRuleViolation when the amount is not the fee of the permit
     */
    public function handle(Permit $permit, User $applicant, PaymentData $data): Payment
    {
        $disk = Storage::disk(config('permits.documents_disk'));
        $path = null;

        if ($data->receiptFile !== null) {
            // A random name: nothing about the applicant or the file they chose reaches the disk.
            $extension = $data->receiptFile->guessExtension() ?? 'bin';
            $path = $disk->putFileAs('receipts', $data->receiptFile, Str::random(40).'.'.$extension);

            if ($path === false) {
                throw new PermitRuleViolation('receipt_not_stored');
            }
        }

        try {
            $payment = null;

            $this->transitions->apply($permit, PermitStatus::PaymentSubmitted, $applicant, null, function (Permit $permit) use ($applicant, $data, $path, &$payment): void {
                if ($permit->fee_cents === null || $data->amountCents !== $permit->fee_cents) {
                    throw new PermitRuleViolation('amount_mismatch');
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
                $payment->submitted_by = $applicant->id;
                $payment->receipt_file_path = $path;
                $payment->status = PaymentStatus::Submitted;
                $payment->save();
            });

            /** @var Payment $payment */
            return $payment;
        } catch (Throwable $exception) {
            // Nothing was recorded, so the uploaded file must not stay behind.
            $this->discard($path);

            // Two submissions of the same receipt raced; the unique index decided.
            if ($exception instanceof QueryException && $exception->getCode() === self::UNIQUE_VIOLATION) {
                throw new DuplicateReceipt($data->receiptNumber);
            }

            throw $exception;
        }
    }

    private function discard(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(config('permits.documents_disk'))->delete($path);
        }
    }
}
