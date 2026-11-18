<?php

namespace App\Actions\Letters;

use App\Data\PaymentData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Exceptions\DuplicateReceipt;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\RequestLetter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * One receipt for every approved vehicle of a request letter: the cashier
 * records the bank receipt once, for the total of the fees, and each permit
 * gets its own verified payment of its own fee under that receipt — as
 * RecordPayment does for one permit, with the same rules (separation of
 * duties, a receipt not used for anything else).
 */
final class RecordLetterPayment
{
    public function __construct(
        private readonly LetterPermits $letters,
        private readonly PermitTransitions $transitions,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @return list<Payment>
     *
     * @throws PermitRuleViolation when nothing waits for payment, or the amount is not the total
     * @throws DuplicateReceipt
     */
    public function handle(RequestLetter $letter, User $cashier, PaymentData $data, ?string $note = null): array
    {
        Gate::forUser($cashier)->authorize(Permission::VerifyPayments->value);

        $permits = $letter->permits()->where('status', PermitStatus::AwaitingPayment)->with('vehicles')->get()->all();

        if ($permits === []) {
            throw new PermitRuleViolation('letter_nothing_to_pay');
        }

        $total = array_sum(array_map(fn (Permit $permit): int => (int) $permit->fee_cents, $permits));

        if ($data->amountCents !== $total) {
            throw new PermitRuleViolation('amount_mismatch');
        }

        $disk = Storage::disk(config('permits.documents_disk'));
        $path = null;

        if ($data->receiptFile !== null) {
            $path = $disk->putFileAs('receipts', $data->receiptFile, Str::random(40).'.'.($data->receiptFile->guessExtension() ?? 'bin'));

            if ($path === false) {
                throw new PermitRuleViolation('receipt_not_stored');
            }
        }

        try {
            // The receipt may pay for this letter's vehicles only.
            if (Payment::query()
                ->where('receipt_number', $data->receiptNumber)
                ->where('status', '!=', PaymentStatus::Rejected)
                ->where(fn ($query) => $query->whereNull('request_letter_id')->orWhere('request_letter_id', '!=', $letter->id))
                ->exists()
            ) {
                throw new DuplicateReceipt($data->receiptNumber);
            }

            $payments = $this->letters->each($permits, function (Permit $permit) use ($letter, $cashier, $data, $path, $note): Payment {
                Gate::forUser($cashier)->authorize('recordLetterPayment', $permit);
                $payment = null;

                $this->transitions->apply($permit, PermitStatus::PaymentConfirmed, $cashier, $note, function (Permit $permit) use ($letter, $cashier, $data, $path, $note, &$payment): void {
                    if ($permit->fee_cents === null) {
                        throw new PermitRuleViolation('amount_mismatch');
                    }

                    // Separation of duties (spec §2): approving and collecting are two people's jobs.
                    if (config('permits.separation_of_duties') && $permit->approved_by === $cashier->id) {
                        throw new PermitRuleViolation('verifier_is_approver');
                    }

                    $payment = new Payment([
                        'method' => $data->method,
                        'receipt_number' => $data->receiptNumber,
                        'amount_cents' => $permit->fee_cents,
                        'paid_at' => $data->paidAt->toDateString(),
                    ]);
                    $payment->permit_id = $permit->id;
                    $payment->request_letter_id = $letter->id;
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
                            'request_letter' => $letter->id,
                        ],
                    );
                });

                /** @var Payment $payment */
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

        return $payments;
    }
}
