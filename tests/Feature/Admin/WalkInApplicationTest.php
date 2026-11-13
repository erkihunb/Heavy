<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Database\Factories\PermitFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A request taken at the office, as the Registrar works on it under
 * Permits: open it, correct it, register a vehicle it needs, submit it and
 * hand in the payment receipt — with the applicant's own rules.
 */

beforeEach(function () {
    Storage::fake('documents');
    $this->registrar = staffUser(Role::Registrar);
    $this->applicant = applicantWithOrganization();
});

/**
 * A request of the applicant's organization, with one of its vehicles.
 */
function officeRequest(User $applicant, ?PermitFactory $factory = null): Permit
{
    $vehicle = Vehicle::factory()->for($applicant->organization)->create();

    return ($factory ?? Permit::factory()->draft())
        ->forVehicles($vehicle)
        ->create(['applicant_id' => $applicant->id]);
}

it('opens the request from Permits with what may be done to it', function () {
    $permit = officeRequest($this->applicant);

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.show', $permit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/show')
            ->where('applicant.id', $this->applicant->id)
            ->where('permit.id', $permit->id)
            ->where('actions', ['update' => true, 'submit' => true, 'submitPayment' => false]));
});

it('registers a vehicle the request needs in its organization\'s register', function () {
    $permit = officeRequest($this->applicant);
    $type = VehicleType::factory()->create();

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.vehicles.create', $permit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/vehicle-create')
            ->where('permit.id', $permit->id));

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.vehicles.store', $permit), [
            'plate' => 'ኢት-03-40134',
            'vehicle_type_id' => $type->id,
            'owner_name' => 'አበበ ከበደ',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.permits.edit', $permit));

    expect(Vehicle::query()->where('vehicle_type_id', $type->id)->sole()->organization_id)->toBe($this->applicant->organization_id);
});

it('checks the plate against the organization\'s register', function () {
    $permit = officeRequest($this->applicant);
    Vehicle::factory()->for($this->applicant->organization)->withPlate('ኢት-03-40134')->create();

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.vehicles.store', $permit), [
            'plate' => 'ኢት-03-40134',
            'vehicle_type_id' => VehicleType::factory()->create()->id,
            'owner_name' => 'አበበ ከበደ',
        ])
        ->assertSessionHasErrors('plate');
});

it('edits and submits the request for the applicant', function () {
    $permit = officeRequest($this->applicant);
    $payload = [...permitPayload($this->applicant), ...letterFields()];

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.edit', $permit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/edit')
            ->has('vehicles', 2));

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $permit), [...$payload, 'reason' => 'ሌላ ምክንያት'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.permits.show', $permit));

    expect($permit->refresh()->reason)->toBe('ሌላ ምክንያት');

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.submit', $permit))
        ->assertRedirect(route('admin.permits.show', $permit));

    // The request is the applicant's; the history names the Registrar.
    expect($permit->refresh()->status)->toBe(PermitStatus::Submitted)
        ->and($permit->applicant_id)->toBe($this->applicant->id)
        ->and(PermitStatusTransition::query()->where('permit_id', $permit->id)->latest('id')->first()->actor_id)->toBe($this->registrar->id);

    // Submitted: no more changes from the office.
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.submit', $permit))
        ->assertForbidden();

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.edit', $permit))
        ->assertForbidden();
});

it('offers only the organization\'s own vehicles', function () {
    $permit = officeRequest($this->applicant);
    $foreign = Vehicle::factory()->create();

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $permit), [...permitPayload($this->applicant, ['primary_vehicle_id' => $foreign->id]), ...letterFields()])
        ->assertSessionHasErrors('primary_vehicle_id');
});

it('estimates the fee for the organization\'s vehicle', function () {
    withFeeSchedule();
    $permit = officeRequest($this->applicant);
    $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();

    $this->actingAs($this->registrar)
        ->getJson(route('admin.permits.fee-estimate', [
            'permit' => $permit->id,
            'permit_type' => 'full_day',
            'primary_vehicle_id' => $vehicle->id,
            'valid_from' => now()->addDays(2)->toDateString(),
            'valid_to' => now()->addDays(4)->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('days', 3);
});

it('hands in the payment receipt of an approved request', function () {
    $permit = officeRequest($this->applicant, Permit::factory()->awaitingPayment()->state(['fee_cents' => 600_050]));

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.payment', $permit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/permits/payment'));

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.payments.store', $permit), [
            'method' => 'bank_deposit',
            'receipt_number' => 'FS228740',
            'amount' => '6000.50',
            'paid_at' => now(config('localization.display_timezone'))->subDay()->toDateString(),
            'receipt_file' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n0\n%%EOF\n"),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.permits.show', $permit));

    expect($permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted)
        ->and(Payment::query()->sole()->submitted_by)->toBe($this->registrar->id);
});

it('lets the registrar print an issued permit', function () {
    $permit = Permit::factory()->issued()->create(['applicant_id' => $this->applicant->id, 'organization_id' => $this->applicant->organization_id]);

    expect($this->registrar->can('download', $permit))->toBeTrue()
        ->and($this->registrar->can('download', Permit::factory()->draft()->create()))->toBeFalse();
});

it('finds no request whose applicant is not an applicant of its organization', function (Closure $applicant) {
    $permit = Permit::factory()->draft()->create(['applicant_id' => $applicant()->id]);

    $this->actingAs($this->registrar)->get(route('admin.permits.show', $permit))->assertNotFound();
    $this->actingAs($this->registrar)->post(route('admin.permits.submit', $permit))->assertNotFound();
})->with([
    'a staff member' => [fn () => staffUser(Role::Cashier)],
    'an applicant of another organization' => [fn () => applicantWithOrganization()],
    'an applicant without an organization' => [fn () => userWithRole(Role::Applicant)],
]);

it('is for registrars and admins only', function (Role $role, int $status) {
    $permit = officeRequest($this->applicant);

    $this->actingAs(staffUser($role))
        ->get(route('admin.permits.show', $permit))
        ->assertStatus($status);

    $this->actingAs(staffUser($role))
        ->put(route('admin.permits.update', $permit), [...permitPayload($this->applicant), ...letterFields()])
        ->assertStatus($status === 200 ? 302 : $status);
})->with([
    'registrar' => [Role::Registrar, 200],
    'admin' => [Role::Admin, 200],
    'reviewer' => [Role::Reviewer, 403],
    'cashier' => [Role::Cashier, 403],
    'traffic officer' => [Role::TrafficOfficer, 403],
]);

it('never lets an applicant use the office routes', function () {
    assertSignedOut($this->actingAs($this->applicant)
        ->get(route('admin.permits.show', officeRequest($this->applicant))));
});
