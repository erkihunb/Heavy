<?php

use App\Actions\Permits\RejectPayment;
use App\Actions\Permits\SubmitPayment;
use App\Actions\Permits\VerifyPayment;
use App\Data\PaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Exceptions\DuplicateReceipt;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * SubmitPayment, VerifyPayment and RejectPayment: the status each leaves the
 * permit and the payment in, the history row, and the files on the private
 * disk. Separation of duties (spec §2) is enforced here.
 */

beforeEach(function () {
    Storage::fake('documents');

    $this->applicant = applicantWithOrganization();
    $this->vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    $this->cashier = userWithRole(Role::Cashier);
    $this->permit = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create(['fee_cents' => 600_000]);
});

/**
 * A PDF the content sniffer recognises.
 */
function receiptPdf(string $name = 'receipt.pdf', int $paddingKilobytes = 1): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat('0', $paddingKilobytes * 1024)."\n%%EOF\n");
}

function paymentData(int $cents = 600_000, string $receipt = 'FS228740', ?UploadedFile $file = null): PaymentData
{
    return new PaymentData(
        method: PaymentMethod::BankDeposit,
        receiptNumber: $receipt,
        amountCents: $cents,
        paidAt: CarbonImmutable::parse('2026-11-10', 'UTC'),
        receiptFile: $file ?? receiptPdf(),
    );
}

/**
 * @return list<array{0: string|null, 1: string, 2: int|null, 3: string|null}>
 */
function paymentHistory(Permit $permit): array
{
    return PermitStatusTransition::query()
        ->where('permit_id', $permit->id)
        ->orderBy('id')
        ->get()
        ->map(fn (PermitStatusTransition $row): array => [$row->from_status?->value, $row->to_status->value, $row->actor_id, $row->note])
        ->all();
}

/**
 * A permit waiting for verification with its submitted payment.
 *
 * @return array{0: Permit, 1: Payment}
 */
function submittedPayment(?User $approver = null): array
{
    $permit = Permit::factory()->paymentSubmitted()->create(array_filter(['approved_by' => $approver?->id]));

    return [$permit, Payment::factory()->forPermit($permit)->create()];
}

describe('SubmitPayment', function () {
    it('records the receipt and waits for a cashier', function () {
        $payment = app(SubmitPayment::class)->handle($this->permit, $this->applicant, paymentData());

        $this->permit->refresh();
        $payment->refresh();

        expect($this->permit->status)->toBe(PermitStatus::PaymentSubmitted)
            ->and($payment->permit_id)->toBe($this->permit->id)
            ->and($payment->status)->toBe(PaymentStatus::Submitted)
            ->and($payment->method)->toBe(PaymentMethod::BankDeposit)
            ->and($payment->receipt_number)->toBe('FS228740')
            ->and($payment->amount_cents)->toBe(600_000)
            ->and($payment->paid_at->toDateString())->toBe('2026-11-10')
            ->and($payment->submitted_by)->toBe($this->applicant->id)
            ->and($payment->verified_by)->toBeNull()
            ->and(paymentHistory($this->permit))->toBe([['awaiting_payment', 'payment_submitted', $this->applicant->id, null]]);
    });

    it('stores the receipt file privately under a random name', function () {
        $payment = app(SubmitPayment::class)->handle($this->permit, $this->applicant, paymentData(file: receiptPdf('ደረሰኝ abebe.pdf')));

        expect($payment->receipt_file_path)->toMatch('#^receipts/[A-Za-z0-9]{40}\.pdf$#')
            ->and($payment->receipt_file_path)->not->toContain('abebe');

        Storage::disk('documents')->assertExists($payment->receipt_file_path);
    });

    it('refuses an amount that is not the fee, and keeps no file', function (int $cents) {
        expect(fn () => app(SubmitPayment::class)->handle($this->permit, $this->applicant, paymentData(cents: $cents)))
            ->toThrow(fn (PermitRuleViolation $e) => expect($e->rule)->toBe('amount_mismatch'));

        expect($this->permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and(Payment::query()->count())->toBe(0)
            ->and(Storage::disk('documents')->allFiles())->toBe([]);
    })->with([599_999, 600_001, 0]);

    it('refuses a receipt that already paid for something, and keeps no file', function () {
        Payment::factory()->create(['receipt_number' => 'FS228740']);

        expect(fn () => app(SubmitPayment::class)->handle($this->permit, $this->applicant, paymentData()))
            ->toThrow(DuplicateReceipt::class);

        expect($this->permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and(Payment::query()->where('permit_id', $this->permit->id)->count())->toBe(0)
            ->and(Storage::disk('documents')->allFiles())->toBe([]);
    });

    it('refuses while the permit is not waiting for payment, and keeps no file', function (string $state) {
        // Another vehicle: a second blocking permit on the same one would overlap.
        $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
        $permit = Permit::factory()->forVehicles($vehicle)->{$state}()->create(['fee_cents' => 600_000]);

        expect(fn () => app(SubmitPayment::class)->handle($permit, $this->applicant, paymentData()))
            ->toThrow(InvalidPermitTransition::class);

        expect(Payment::query()->count())->toBe(0)
            ->and(Storage::disk('documents')->allFiles())->toBe([]);
    })->with(['underReview', 'paymentSubmitted', 'issued', 'revoked']);
});

describe('VerifyPayment', function () {
    it('verifies the payment and leaves the issuing to an Issuer', function () {
        [$permit, $payment] = submittedPayment();
        $this->freezeSecond();

        app(VerifyPayment::class)->handle($payment, $this->cashier, 'ደረሰኙ ተረጋግጧል');

        $permit->refresh();
        $payment->refresh();

        expect($permit->status)->toBe(PermitStatus::PaymentConfirmed)
            ->and($permit->issued_at)->toBeNull()
            ->and($permit->verification_token_hash)->toBeNull()
            ->and($payment->status)->toBe(PaymentStatus::Verified)
            ->and($payment->verified_by)->toBe($this->cashier->id)
            ->and($payment->verified_at?->equalTo(now()))->toBeTrue()
            ->and($payment->notes)->toBe('ደረሰኙ ተረጋግጧል')
            ->and(paymentHistory($permit))->toBe([['payment_submitted', 'payment_confirmed', $this->cashier->id, 'ደረሰኙ ተረጋግጧል']]);
    });

    it('does not let the person who approved the permit verify its payment', function () {
        [$permit, $payment] = submittedPayment(approver: $this->cashier);

        expect(fn () => app(VerifyPayment::class)->handle($payment, $this->cashier))
            ->toThrow(fn (PermitRuleViolation $e) => expect($e->rule)->toBe('verifier_is_approver'));

        expect($permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted)
            ->and($payment->refresh()->status)->toBe(PaymentStatus::Submitted)
            ->and(paymentHistory($permit))->toBe([]);
    });

    it('allows it when separation of duties is switched off', function () {
        config(['permits.separation_of_duties' => false]);
        [$permit, $payment] = submittedPayment(approver: $this->cashier);

        app(VerifyPayment::class)->handle($payment, $this->cashier);

        expect($permit->refresh()->status)->toBe(PermitStatus::PaymentConfirmed);
    });

    it('refuses a payment that is no longer waiting', function () {
        [$permit, $current] = submittedPayment();
        $old = Payment::factory()->forPermit($permit)->rejected()->create();

        expect(fn () => app(VerifyPayment::class)->handle($old, $this->cashier))
            ->toThrow(fn (PermitRuleViolation $e) => expect($e->rule)->toBe('payment_not_pending'));

        expect($permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted)
            ->and($old->refresh()->status)->toBe(PaymentStatus::Rejected)
            ->and($current->refresh()->status)->toBe(PaymentStatus::Submitted);
    });

    it('cannot verify twice', function () {
        [$permit, $payment] = submittedPayment();
        app(VerifyPayment::class)->handle($payment, $this->cashier);

        expect(fn () => app(VerifyPayment::class)->handle($payment, userWithRole(Role::Cashier)))
            ->toThrow(InvalidPermitTransition::class);

        expect($payment->refresh()->verified_by)->toBe($this->cashier->id);
    });
});

describe('RejectPayment', function () {
    it('rejects the payment and puts the permit back to waiting for payment', function () {
        [$permit, $payment] = submittedPayment();

        app(RejectPayment::class)->handle($payment, $this->cashier, '  ደረሰኙ አይነበብም  ');

        $permit->refresh();
        $payment->refresh();

        expect($permit->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($permit->issued_at)->toBeNull()
            ->and($payment->status)->toBe(PaymentStatus::Rejected)
            ->and($payment->verified_by)->toBe($this->cashier->id)
            ->and($payment->notes)->toBe('ደረሰኙ አይነበብም')
            ->and(paymentHistory($permit))->toBe([['payment_submitted', 'awaiting_payment', $this->cashier->id, 'ደረሰኙ አይነበብም']]);
    });

    it('lets the applicant pay again, even with the same receipt number', function () {
        $payment = app(SubmitPayment::class)->handle($this->permit, $this->applicant, paymentData());
        app(RejectPayment::class)->handle($payment, $this->cashier, 'The scan is unreadable.');

        $again = app(SubmitPayment::class)->handle($this->permit->refresh(), $this->applicant, paymentData());

        expect($again->id)->not->toBe($payment->id)
            ->and($this->permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted)
            ->and(Payment::query()->where('permit_id', $this->permit->id)->count())->toBe(2);
    });

    it('refuses a payment that is no longer waiting', function () {
        [$permit, $payment] = submittedPayment();
        $old = Payment::factory()->forPermit($permit)->rejected()->create();

        expect(fn () => app(RejectPayment::class)->handle($old, $this->cashier, 'Again.'))
            ->toThrow(fn (PermitRuleViolation $e) => expect($e->rule)->toBe('payment_not_pending'));

        expect($permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted);
    });
});
