<?php

use App\Domain\Permits\ValidityRules;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Jobs\GeneratePermitDocument;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * The office's application form in the order of the Authority's form: the
 * applicant, the vehicle (an "other" one described in words, a trailer by its
 * plate), the permit, the receipt brought with the request, and the
 * confirmation. The receipt waits for the decision, then goes to the cashier.
 */

beforeEach(function () {
    config(['permits.two_step_approval' => false]);
    Notification::fake();
    Bus::fake([GeneratePermitDocument::class]);
    withFeeSchedule(150_000);

    $this->registrar = staffUser(Role::Registrar);
    $this->type = VehicleType::factory()->create(['code' => 'cargo']);
    $this->trailer = VehicleType::factory()->create(['code' => VehicleType::TRAILER]);
    $this->other = VehicleType::factory()->create(['code' => VehicleType::OTHER]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function intakeForm(array $overrides = []): array
{
    $today = ValidityRules::today();
    $letter = letterFields();

    return [
        'contact_email' => 'abebe@example.test',
        'contact_name' => 'Abebe Kebede',
        'phone' => '0911223344',
        'name_am' => 'አበበ የጭነት ማጓጓዣ',
        'tin' => '0012345678',
        'locale' => 'am',
        'apply' => true,
        'submit' => true,
        'vehicles' => [
            ['plate' => 'ኢት-03-40134', 'vehicle_type_id' => VehicleType::query()->where('code', 'cargo')->value('id'), 'owner_name' => 'አበበ ከበደ'],
            ['plate' => 'ኢት-03-40135', 'vehicle_type_id' => VehicleType::query()->where('code', VehicleType::TRAILER)->value('id'), 'owner_name' => 'አበበ ከበደ'],
        ],
        'permit_type' => 'full_day',
        'reason' => 'የግንባታ ዕቃ ማጓጓዝ',
        'valid_from' => $today->addDays(3)->toDateString(),
        'valid_to' => $today->addDays(12)->toDateString(),
        ...$letter,
        'amount' => '1500.00',
        'receipt_number' => 'CBE-778899',
        'paid_at' => $today->toDateString(),
        'receipt_file' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
        'confirmed' => true,
        ...$overrides,
    ];
}

it('records the request with the receipt, which waits for the decision', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), intakeForm())
        ->assertSessionHasNoErrors();

    $permit = Permit::query()->with('vehicles')->sole();
    $payment = Payment::query()->sole();

    expect($permit->status)->toBe(PermitStatus::Submitted)
        ->and($permit->vehicles)->toHaveCount(2)
        ->and($payment->permit_id)->toBe($permit->id)
        ->and($payment->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->amount_cents)->toBe(150_000)
        ->and($payment->submitted_by)->toBe($this->registrar->id)
        ->and($payment->receipt_file_path)->toStartWith('receipts/')
        ->and(Activity::query()->where('event', 'payment.taken_at_intake')->count())->toBe(1);

    // Not the cashier's yet: the request has not been decided.
    $this->actingAs(staffUser(Role::Cashier))
        ->get(route('staff.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 0));
});

it('sends the receipt to the cashier once the request is approved for the same fee', function () {
    $this->actingAs($this->registrar)->post(route('admin.permits.store'), intakeForm())->assertSessionHasNoErrors();
    $permit = Permit::query()->sole();
    $reviewer = staffUser(Role::Reviewer);
    $cashier = staffUser(Role::Cashier);

    $this->actingAs($reviewer)->post(route('staff.permits.start-review', $permit))->assertSessionHas('success');
    $this->actingAs($reviewer)->post(route('staff.permits.approve', $permit))->assertSessionHas('success');

    expect($permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted)
        ->and($permit->fee_cents)->toBe(150_000);

    $this->actingAs($cashier)
        ->get(route('staff.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));

    $this->actingAs($cashier)
        ->post(route('staff.payments.verify', Payment::query()->sole()))
        ->assertSessionHasNoErrors();

    expect($permit->refresh()->status)->toBe(PermitStatus::PaymentConfirmed)
        ->and(Payment::query()->sole()->status)->toBe(PaymentStatus::Verified);
});

it('sets the receipt aside when the fee fixed at approval is another amount', function () {
    $this->actingAs($this->registrar)->post(route('admin.permits.store'), intakeForm())->assertSessionHasNoErrors();
    $permit = Permit::query()->sole();
    $reviewer = staffUser(Role::Reviewer);

    // As if the schedule had changed between the desk and the decision.
    Payment::query()->update(['amount_cents' => 100_000]);

    $this->actingAs($reviewer)->post(route('staff.permits.start-review', $permit));
    $this->actingAs($reviewer)->post(route('staff.permits.approve', $permit))->assertSessionHas('success');

    $payment = Payment::query()->sole();

    expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
        ->and($payment->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->notes)->toContain('1,000.00');
});

it('keeps the cashier\'s desk payment when no receipt is brought', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), intakeForm([
            'amount' => '', 'receipt_number' => '', 'paid_at' => '', 'receipt_file' => null,
        ]))
        ->assertSessionHasNoErrors();

    expect(Permit::query()->sole()->status)->toBe(PermitStatus::Submitted)
        ->and(Payment::query()->count())->toBe(0);
});

it('wants the whole receipt once part of it is given, and the amount of the fee', function (array $overrides, string $field) {
    // Dates are known only once the application has started.
    if ($overrides === ['paid_at' => 'tomorrow']) {
        $overrides['paid_at'] = ValidityRules::today()->addDay()->toDateString();
    }

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), intakeForm($overrides))
        ->assertSessionHasErrors($field);

    expect(Permit::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
})->with([
    'no file' => [['receipt_file' => null], 'receipt_file'],
    'no date' => [['paid_at' => ''], 'paid_at'],
    'not the fee' => [['amount' => '1000.00'], 'amount'],
    'paid in the future' => [['paid_at' => 'tomorrow'], 'paid_at'],
]);

it('asks for the confirmation when submitting, not for a draft', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), intakeForm(['confirmed' => false]))
        ->assertSessionHasErrors('confirmed');

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), intakeForm(['confirmed' => false, 'submit' => false]))
        ->assertSessionHasNoErrors();

    // A draft keeps no receipt.
    expect(Permit::query()->sole()->status)->toBe(PermitStatus::Draft)
        ->and(Payment::query()->count())->toBe(0);
});

it('describes an "other" vehicle in words', function () {
    $form = fn (string $detail) => intakeForm(['vehicles' => [
        ['plate' => 'ኢት-03-40134', 'vehicle_type_id' => $this->other->id, 'owner_name' => 'አበበ ከበደ', 'type_detail' => $detail],
    ]]);

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), $form(''))
        ->assertSessionHasErrors('vehicles.0.type_detail');

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), $form('ክሬን'))
        ->assertSessionHasNoErrors();

    $vehicle = Vehicle::query()->with('vehicleType')->sole();

    expect($vehicle->type_detail)->toBe('ክሬን')
        ->and($vehicle->typeName('am'))->toEndWith('— ክሬን');
});

it('uses the applicant e-mail and no address for the organization', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), intakeForm())
        ->assertSessionHasNoErrors();

    $organization = Organization::query()->sole();

    expect($organization->email)->toBe('abebe@example.test')
        ->and($organization->address)->toBe('');
});

it('tells the desk the fee by the vehicle type', function () {
    $today = ValidityRules::today();

    $this->actingAs($this->registrar)
        ->getJson(route('admin.permits.intake-fee', [
            'permit_type' => 'full_day',
            'vehicle_type_id' => $this->type->id,
            'valid_from' => $today->addDays(3)->toDateString(),
            'valid_to' => $today->addDays(12)->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('days', 10)
        ->assertJsonPath('fee.amount_cents', 150_000);

    $this->actingAs(applicantWithOrganization())
        ->getJson(route('admin.permits.intake-fee', ['permit_type' => 'full_day']))
        ->assertForbidden();
});
