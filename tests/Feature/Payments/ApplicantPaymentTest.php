<?php

use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Role;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The applicant pays the fee of an approved permit by submitting the receipt
 * (spec §11), and sees a live estimate of the fee while applying.
 */

beforeEach(function () {
    Storage::fake('documents');

    $this->user = applicantWithOrganization();
    $this->vehicle = Vehicle::factory()->for($this->user->organization)->create();
    $this->permit = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create(['fee_cents' => 600_050]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentForm(array $overrides = []): array
{
    return [
        'method' => 'bank_deposit',
        'receipt_number' => 'FS228740',
        'amount' => '6000.50',
        'paid_at' => now(config('localization.display_timezone'))->subDay()->toDateString(),
        'receipt_file' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n0\n%%EOF\n"),
        ...$overrides,
    ];
}

/**
 * An upload backed by a real file. Laravel's fake uploads report a MIME type
 * from their name, so only a real file shows what the content sniffer decides.
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'receipt');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

describe('submitting a receipt', function () {
    it('records the payment and returns to the permit', function () {
        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $this->permit), paymentForm())
            ->assertRedirect(route('permits.show', $this->permit))
            ->assertSessionHas('success', __('payments.messages.submitted'));

        $payment = Payment::query()->sole();

        expect($this->permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted)
            ->and($payment->amount_cents)->toBe(600_050)
            ->and($payment->status)->toBe(PaymentStatus::Submitted);

        Storage::disk('documents')->assertExists($payment->receipt_file_path);
    });

    it('accepts the amount written with separators', function (string $amount) {
        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $this->permit), paymentForm(['amount' => $amount]))
            ->assertSessionHasNoErrors();
    })->with(['6000.50', '6,000.50', ' 6000.5 ']);

    it('accepts a PNG or JPEG photo of the receipt', function (string $name) {
        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $this->permit), paymentForm(['receipt_file' => UploadedFile::fake()->image($name)]))
            ->assertSessionHasNoErrors();
    })->with(['receipt.png', 'receipt.jpg']);

    it('validates the receipt', function (array $overrides, string $field) {
        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $this->permit), paymentForm($overrides))
            ->assertSessionHasErrors($field);

        expect(Payment::query()->count())->toBe(0)
            ->and($this->permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and(Storage::disk('documents')->allFiles())->toBe([]);
    })->with([
        'no method' => [['method' => ''], 'method'],
        'legacy is not for applicants' => [['method' => 'legacy'], 'method'],
        'no receipt number' => [['receipt_number' => ''], 'receipt_number'],
        'odd receipt number' => [['receipt_number' => '#228740'], 'receipt_number'],
        'amount in words' => [['amount' => 'ስድስት ሺህ'], 'amount'],
        'amount below the fee' => [['amount' => '6000'], 'amount'],
        'amount above the fee' => [['amount' => '6000.51'], 'amount'],
        'no date' => [['paid_at' => ''], 'paid_at'],
        'date in the future' => [['paid_at' => now()->addDays(2)->toDateString()], 'paid_at'],
        'no file' => [['receipt_file' => null], 'receipt_file'],
    ]);

    it('judges the file by its content, not its name', function (string $content) {
        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $this->permit), paymentForm(['receipt_file' => realUpload('receipt.pdf', $content)]))
            ->assertSessionHasErrors(['receipt_file' => __('payments.errors.file_type')]);

        expect(Payment::query()->count())->toBe(0);
    })->with([
        'text' => ['just text, not a receipt'],
        'html' => ['<html><script>alert(1)</script></html>'],
        'php' => ['<?php echo 1;'],
        'program' => ["MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00".str_repeat("\x00", 64)],
    ]);

    it('accepts a real PDF whatever it is called', function () {
        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $this->permit), paymentForm(['receipt_file' => realUpload('scan.jpg', "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF\n")]))
            ->assertSessionHasNoErrors();

        expect(Payment::query()->sole()->receipt_file_path)->toEndWith('.pdf');
    });

    it('names the fee when the amount is wrong', function () {
        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $this->permit), paymentForm(['amount' => '1000']))
            ->assertSessionHasErrors(['amount' => __('payments.errors.amount_mismatch', ['fee' => 'ETB 6,000.50'])]);
    });

    it('refuses a file over the size limit', function () {
        config(['permits.receipt_max_kilobytes' => 100]);
        $large = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n".str_repeat('0', 150 * 1024)."\n%%EOF\n");

        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $this->permit), paymentForm(['receipt_file' => $large]))
            ->assertSessionHasErrors('receipt_file');
    });

    it('is forbidden while the permit is not waiting for payment', function (string $state) {
        $permit = Permit::factory()->forVehicles(Vehicle::factory()->for($this->user->organization)->create())->{$state}()->create();

        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $permit), paymentForm())
            ->assertForbidden();

        expect(Payment::query()->where('permit_id', $permit->id)->count())->toBe(0);
    })->with(['draft', 'underReview', 'paymentSubmitted', 'issued']);

    it('pretends another organization\'s permit does not exist', function () {
        $other = Permit::factory()->awaitingPayment()->create();

        $this->actingAs($this->user)
            ->post(route('permits.payments.store', $other), paymentForm(['amount' => '1000']))
            ->assertNotFound();

        expect(Payment::query()->count())->toBe(0);
    });

    it('is not for staff', function () {
        $this->actingAs(staffUser(Role::Cashier))
            ->post(route('permits.payments.store', $this->permit), paymentForm())
            ->assertForbidden();
    });
});

describe('the permit page', function () {
    it('offers the payment form with the methods and the size limit', function () {
        $this->actingAs($this->user)
            ->get(route('permits.show', $this->permit))
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/permits/show')
                ->where('permit.can.submitPayment', true)
                ->where('paymentMethods', ['bank_deposit', 'cash'])
                ->where('receiptMaxKilobytes', 5120)
                ->has('permit.payments', 0));
    });

    it('lists the payments with the cashier\'s reason for a rejection', function () {
        Payment::factory()->forPermit($this->permit)->rejected(note: 'የማይነበብ ደረሰኝ')->create();

        $this->actingAs($this->user)
            ->get(route('permits.show', $this->permit))
            ->assertInertia(fn (Assert $page) => $page
                ->has('permit.payments', 1)
                ->where('permit.payments.0.status', 'rejected')
                ->where('permit.payments.0.notes', 'የማይነበብ ደረሰኝ')
                ->missing('permit.payments.0.receipt_file_path')
                ->missing('permit.payments.0.decided_by'));
    });
});

describe('fee estimate', function () {
    beforeEach(function () {
        FeeSchedule::factory()->days(1, 30)->amount(100_000)->create();
        FeeSchedule::factory()->forType(PermitType::Pass)->days(1, 30)->amount(50_000)->create();
    });

    function estimateQuery(string $vehicleId, array $overrides = []): array
    {
        return [
            'permit_type' => 'full_day',
            'primary_vehicle_id' => $vehicleId,
            'valid_from' => '2026-12-01',
            'valid_to' => '2026-12-10',
            ...$overrides,
        ];
    }

    it('estimates the fee by today\'s schedule', function () {
        $this->actingAs($this->user)
            ->getJson(route('fee-estimate', estimateQuery($this->vehicle->id)))
            ->assertOk()
            ->assertExactJson([
                'days' => 10,
                'fee' => ['amount_cents' => 100_000, 'currency' => 'ETB', 'formatted' => 'ETB 1,000.00'],
            ]);

        $this->actingAs($this->user)
            ->getJson(route('fee-estimate', estimateQuery($this->vehicle->id, ['permit_type' => 'pass'])))
            ->assertJsonPath('fee.amount_cents', 50_000);
    });

    it('says when there is no fee for the request', function () {
        $this->actingAs($this->user)
            ->getJson(route('fee-estimate', estimateQuery($this->vehicle->id, ['valid_to' => '2027-02-01'])))
            ->assertOk()
            ->assertExactJson(['days' => 63, 'fee' => null]);
    });

    it('uses the type of the chosen vehicle', function () {
        $crane = VehicleType::factory()->create();
        FeeSchedule::factory()->forVehicleType($crane)->amount(1_500_000)->create();
        $vehicle = Vehicle::factory()->for($this->user->organization)->create(['vehicle_type_id' => $crane->id]);

        $this->actingAs($this->user)
            ->getJson(route('fee-estimate', estimateQuery($vehicle->id)))
            ->assertJsonPath('fee.amount_cents', 1_500_000);
    });

    it('refuses another organization\'s vehicle and bad dates', function (array $overrides) {
        $other = Vehicle::factory()->create();

        $this->actingAs($this->user)
            ->getJson(route('fee-estimate', estimateQuery($overrides['own'] ?? true ? $this->vehicle->id : $other->id, $overrides['query'] ?? [])))
            ->assertUnprocessable();
    })->with([
        'other organization' => [['own' => false]],
        'upside-down dates' => [['query' => ['valid_to' => '2026-11-30']]],
        'unknown type' => [['query' => ['permit_type' => 'night']]],
    ]);

    it('is for applicants with an organization only', function () {
        $this->actingAs(staffUser(Role::Reviewer))
            ->getJson(route('fee-estimate', estimateQuery($this->vehicle->id)))
            ->assertForbidden();
    });
});
