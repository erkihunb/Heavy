<?php

use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Role;
use App\Jobs\GeneratePermitDocument;
use App\Models\FeeSchedule;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Registering a permit at a fee rate (owner's decision, 2026-11-18): the
 * office picks a rate from the fee schedule and the start date only; the end
 * date is worked out from the rate's days, and the fee charged at approval is
 * the rate's.
 */

beforeEach(function () {
    config(['permits.max_duration_days' => 90]);
    Notification::fake();
    Bus::fake([GeneratePermitDocument::class]);
    Storage::fake('documents');

    $this->registrar = staffUser(Role::Registrar);
    $this->cargo = VehicleType::factory()->create();
    $this->crane = VehicleType::factory()->create();

    $this->month = FeeSchedule::factory()->days(1, 30)->amount(100_000)->create();
    $this->quarter = FeeSchedule::factory()->days(31, 90)->amount(250_000)->create();
    $this->craneMonth = FeeSchedule::factory()->forVehicleType($this->crane)->days(1, 30)->amount(180_000)->create();
    $this->pass = FeeSchedule::factory()->forType(PermitType::Pass)->days(1, 30)->amount(40_000)->create();
});

/**
 * The one-page form for a new organization with one vehicle of the type.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rateForm(VehicleType $type, array $overrides = []): array
{
    return [
        'contact_name' => 'Abebe Kebede',
        'contact_email' => 'abebe@example.test',
        'locale' => 'am',
        'name_am' => 'አበበ የጭነት ማጓጓዣ',
        'phone' => '0911223344',
        'apply' => true,
        'vehicles' => [['plate' => 'ኢት-03-40134', 'vehicle_type_id' => $type->id, 'owner_name' => 'አበበ ከበደ']],
        'permit_type' => 'full_day',
        'valid_from' => ValidityRules::today()->addDays(3)->toDateString(),
        'reason' => 'የግንባታ ዕቃ ማጓጓዝ',
        ...letterFields(),
        ...$overrides,
    ];
}

it('ends the permit when the rate\'s days are over, whatever end date is sent', function () {
    $from = ValidityRules::today()->addDays(3);

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), rateForm($this->cargo, [
            'fee_schedule_id' => $this->quarter->id,
            'valid_to' => $from->addDays(5)->toDateString(),
        ]))
        ->assertSessionHasNoErrors();

    $permit = Permit::query()->sole();

    // Both ends count: 90 days from the start day.
    expect($permit->valid_from->toDateString())->toBe($from->toDateString())
        ->and($permit->valid_to->toDateString())->toBe($from->addDays(89)->toDateString())
        ->and($permit->durationDays())->toBe(90);
});

it('runs a permit at a vehicle type\'s own rate for its days', function () {
    $from = ValidityRules::today()->addDays(3);

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), rateForm($this->crane, ['fee_schedule_id' => $this->craneMonth->id]))
        ->assertSessionHasNoErrors();

    expect(Permit::query()->sole()->valid_to->toDateString())->toBe($from->addDays(29)->toDateString());
});

it('refuses a rate that is not offered for the permit type and vehicle', function (Closure $line) {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), rateForm($this->cargo, ['fee_schedule_id' => $line()->id]))
        ->assertSessionHasErrors(['fee_schedule_id' => __('permits.errors.rate_not_offered')]);

    expect(Permit::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'abebe@example.test')->exists())->toBeFalse();
})->with([
    'another vehicle type\'s line' => [fn () => test()->craneMonth],
    'another permit type\'s line' => [fn () => test()->pass],
    'a line no longer in force' => [fn () => FeeSchedule::factory()->days(1, 10)->effective('2020-01-01', ValidityRules::today()->subDay())->create()],
    'a line beyond the longest permit' => [fn () => FeeSchedule::factory()->days(91, 365)->create()],
]);

it('asks for a rate and the start date', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), rateForm($this->cargo, ['fee_schedule_id' => '', 'valid_from' => '']))
        ->assertSessionHasErrors(['fee_schedule_id', 'valid_from']);

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), rateForm($this->cargo, ['fee_schedule_id' => $this->month->id, 'valid_from' => '2020-01-01']))
        ->assertSessionHasErrors('valid_from');

    expect(Permit::query()->count())->toBe(0);
});

it('refuses the permit when the schedule offers no rate for it', function () {
    $this->pass->delete();

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), rateForm($this->cargo, ['permit_type' => 'pass', 'fee_schedule_id' => $this->quarter->id]))
        ->assertSessionHasErrors(['permit_type' => __('permits.errors.fee_not_set_for_registration')]);

    expect(Permit::query()->count())->toBe(0);
});

it('charges at approval the fee of the rate picked', function (string $line, int $fee) {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), rateForm($this->cargo, ['fee_schedule_id' => $this->{$line}->id, 'submit' => true]))
        ->assertSessionHasNoErrors();

    $permit = Permit::query()->sole();
    $reviewer = staffUser(Role::Reviewer);

    $this->actingAs($reviewer)->post(route('staff.permits.start-review', $permit))->assertSessionHas('success');
    $this->actingAs($reviewer)->post(route('staff.permits.approve', $permit))->assertSessionHas('success');

    expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
        ->and($permit->fee_cents)->toBe($fee);
})->with([
    '30 days' => ['month', 100_000],
    '90 days' => ['quarter', 250_000],
]);

/*
 * A draft being corrected: the same choice of a rate and the start date.
 */

/**
 * A draft of an applicant's organization with one vehicle of the type, covering $days days.
 */
function rateDraft(User $applicant, VehicleType $type, int $days): Permit
{
    $vehicle = Vehicle::factory()->for($applicant->organization)->create(['vehicle_type_id' => $type->id]);
    $from = ValidityRules::today()->addDays(5);

    return Permit::factory()->draft()->forVehicles($vehicle)
        ->validBetween($from, $from->addDays($days - 1))
        ->create(['applicant_id' => $applicant->id, 'organization_id' => $applicant->organization_id]);
}

it('chooses beforehand the rate whose days are the draft\'s', function (string $type, int $days, ?string $line) {
    $draft = rateDraft(applicantWithOrganization(), $this->{$type}, $days);

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.edit', $draft))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/edit')
            ->where('feeScheduleId', $line === null ? null : $this->{$line}->id));
})->with([
    '30 days, cargo' => ['cargo', 30, 'month'],
    '30 days, crane' => ['crane', 30, 'craneMonth'],
    '90 days' => ['cargo', 90, 'quarter'],
    'no rate of 12 days' => ['cargo', 12, null],
]);

it('corrects a draft to another rate, working out its end date', function () {
    $applicant = applicantWithOrganization();
    $draft = rateDraft($applicant, $this->cargo, 30);
    $from = ValidityRules::today()->addDays(10);

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $draft), [
            ...permitPayload($applicant, [
                'primary_vehicle_id' => $draft->vehicles()->sole()->id,
                'fee_schedule_id' => $this->quarter->id,
                'valid_from' => $from->toDateString(),
                'valid_to' => $from->toDateString(),
            ]),
            ...letterFields(),
        ])
        ->assertSessionHasNoErrors();

    expect($draft->refresh()->valid_from->toDateString())->toBe($from->toDateString())
        ->and($draft->valid_to->toDateString())->toBe($from->addDays(89)->toDateString());
});

it('refuses for a draft a rate not offered for its primary vehicle', function () {
    $applicant = applicantWithOrganization();
    $draft = rateDraft($applicant, $this->cargo, 30);

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $draft), [
            ...permitPayload($applicant, [
                'primary_vehicle_id' => $draft->vehicles()->sole()->id,
                'fee_schedule_id' => $this->craneMonth->id,
            ]),
            ...letterFields(),
        ])
        ->assertSessionHasErrors(['fee_schedule_id' => __('permits.errors.rate_not_offered')]);

    expect($draft->refresh()->durationDays())->toBe(30);
});

it('refuses to correct a draft the schedule offers no rate for', function () {
    $applicant = applicantWithOrganization();
    $draft = rateDraft($applicant, $this->cargo, 30);

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $draft), [
            ...permitPayload($applicant, [
                'permit_type' => 'pass',
                'primary_vehicle_id' => $draft->vehicles()->sole()->id,
                'fee_schedule_id' => $this->pass->id,
            ]),
            ...letterFields(),
        ])
        ->assertSessionHasNoErrors();

    $this->pass->delete();

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $draft), [
            ...permitPayload($applicant, [
                'permit_type' => 'pass',
                'primary_vehicle_id' => $draft->vehicles()->sole()->id,
                'fee_schedule_id' => $this->pass->id,
            ]),
            ...letterFields(),
        ])
        ->assertSessionHasErrors(['permit_type' => __('permits.errors.fee_not_set_for_registration')]);
});
