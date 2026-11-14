<?php

use App\Domain\Permits\ValidityRules;
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
 * plate), the permit and the confirmation. No payment is taken with the
 * request: the fee is paid once the permit is approved.
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
        ...$overrides,
    ];
}

it('records the request without a payment, even when receipt details are sent', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), intakeForm([
            'amount' => '1500.00',
            'receipt_number' => 'CBE-778899',
            'paid_at' => ValidityRules::today()->toDateString(),
            'receipt_file' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
        ]))
        ->assertSessionHasNoErrors();

    $permit = Permit::query()->with('vehicles')->sole();

    expect($permit->status)->toBe(PermitStatus::Submitted)
        ->and($permit->vehicles)->toHaveCount(2)
        ->and(Payment::query()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'payment.taken_at_intake')->count())->toBe(0);

    // Nothing for the cashier until the request is decided.
    $this->actingAs(staffUser(Role::Cashier))
        ->get(route('staff.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 0));
});

it('waits for the payment once the request is approved', function () {
    $this->actingAs($this->registrar)->post(route('admin.permits.store'), intakeForm())->assertSessionHasNoErrors();
    $permit = Permit::query()->sole();
    $reviewer = staffUser(Role::Reviewer);

    $this->actingAs($reviewer)->post(route('staff.permits.start-review', $permit))->assertSessionHas('success');
    $this->actingAs($reviewer)->post(route('staff.permits.approve', $permit))->assertSessionHas('success');

    expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
        ->and($permit->fee_cents)->toBe(150_000)
        ->and(Payment::query()->count())->toBe(0);
});

it('submits the request without a separate confirmation', function () {
    // The form no longer has a "I confirm the information is correct" tick:
    // submitting it is the confirmation.
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), intakeForm())
        ->assertSessionHasNoErrors();

    expect(Permit::query()->sole()->status)->toBe(PermitStatus::Submitted);
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

    assertSignedOut($this->actingAs(applicantWithOrganization())
        ->getJson(route('admin.permits.intake-fee', ['permit_type' => 'full_day'])));
});
