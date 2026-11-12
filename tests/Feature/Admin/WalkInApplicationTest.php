<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The whole application for an applicant who came to the office: the
 * Registrar registers the vehicles, fills in and submits the application,
 * and hands in the payment receipt — with the applicant's own rules.
 */

beforeEach(function () {
    Storage::fake('documents');
    $this->registrar = staffUser(Role::Registrar);
    $this->applicant = applicantWithOrganization();
});

it('shows the applicant with their organization, vehicles and applications', function () {
    $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    Permit::factory()->forVehicles($vehicle)->create(['applicant_id' => $this->applicant->id]);

    $this->actingAs($this->registrar)
        ->get(route('admin.applicants.show', $this->applicant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/applicants/show')
            ->where('applicant.email', $this->applicant->email)
            ->has('vehicles', 1)
            ->has('permits', 1));
});

it('registers a vehicle in the applicant\'s register', function () {
    $type = VehicleType::factory()->create();

    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.vehicles.store', $this->applicant), [
            'plate' => 'ኢት-03-40134',
            'vehicle_type_id' => $type->id,
            'owner_name' => 'አበበ ከበደ',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.applicants.show', $this->applicant));

    expect(Vehicle::query()->sole()->organization_id)->toBe($this->applicant->organization_id);
});

it('checks the plate against the applicant\'s register', function () {
    Vehicle::factory()->for($this->applicant->organization)->withPlate('ኢት-03-40134')->create();

    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.vehicles.store', $this->applicant), [
            'plate' => 'ኢት-03-40134',
            'vehicle_type_id' => VehicleType::factory()->create()->id,
            'owner_name' => 'አበበ ከበደ',
        ])
        ->assertSessionHasErrors('plate');
});

it('starts, edits and submits the application for the applicant', function () {
    $payload = permitPayload($this->applicant);

    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.permits.store', $this->applicant), $payload)
        ->assertSessionHasNoErrors();

    $permit = Permit::query()->sole();

    // The application is the applicant's; the history names the Registrar.
    expect($permit->applicant_id)->toBe($this->applicant->id)
        ->and($permit->organization_id)->toBe($this->applicant->organization_id)
        ->and($permit->status)->toBe(PermitStatus::Draft)
        ->and(PermitStatusTransition::query()->where('permit_id', $permit->id)->sole()->actor_id)->toBe($this->registrar->id);

    $this->actingAs($this->registrar)
        ->get(route('admin.applicants.permits.show', [$this->applicant, $permit]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/applicants/permit-show')
            ->where('actions', ['update' => true, 'submit' => true, 'submitPayment' => false]));

    $this->actingAs($this->registrar)
        ->put(route('admin.applicants.permits.update', [$this->applicant, $permit]), [...$payload, 'reason' => 'ሌላ ምክንያት'])
        ->assertSessionHasNoErrors();

    expect($permit->refresh()->reason)->toBe('ሌላ ምክንያት');

    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.permits.submit', [$this->applicant, $permit]))
        ->assertRedirect(route('admin.applicants.permits.show', [$this->applicant, $permit]));

    expect($permit->refresh()->status)->toBe(PermitStatus::Submitted)
        ->and(PermitStatusTransition::query()->where('permit_id', $permit->id)->latest('id')->first()->actor_id)->toBe($this->registrar->id);

    // Submitted: no more changes from the office.
    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.permits.submit', [$this->applicant, $permit]))
        ->assertForbidden();
});

it('offers only the applicant\'s own vehicles', function () {
    $foreign = Vehicle::factory()->create();

    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.permits.store', $this->applicant), permitPayload($this->applicant, ['primary_vehicle_id' => $foreign->id]))
        ->assertSessionHasErrors('primary_vehicle_id');
});

it('estimates the fee for the applicant\'s vehicle', function () {
    withFeeSchedule();
    $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();

    $this->actingAs($this->registrar)
        ->getJson(route('admin.applicants.fee-estimate', [
            'applicant' => $this->applicant->id,
            'permit_type' => 'full_day',
            'primary_vehicle_id' => $vehicle->id,
            'valid_from' => now()->addDays(2)->toDateString(),
            'valid_to' => now()->addDays(4)->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('days', 3);
});

it('hands in the payment receipt of an approved application', function () {
    $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    $permit = Permit::factory()->forVehicles($vehicle)->awaitingPayment()->create(['fee_cents' => 600_050, 'applicant_id' => $this->applicant->id]);

    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.permits.payments.store', [$this->applicant, $permit]), [
            'method' => 'bank_deposit',
            'receipt_number' => 'FS228740',
            'amount' => '6000.50',
            'paid_at' => now(config('localization.display_timezone'))->subDay()->toDateString(),
            'receipt_file' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n0\n%%EOF\n"),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.applicants.permits.show', [$this->applicant, $permit]));

    expect($permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted)
        ->and(Payment::query()->sole()->submitted_by)->toBe($this->registrar->id);
});

it('lets the registrar print an issued permit', function () {
    $permit = Permit::factory()->issued()->create(['applicant_id' => $this->applicant->id, 'organization_id' => $this->applicant->organization_id]);

    expect($this->registrar->can('download', $permit))->toBeTrue()
        ->and($this->registrar->can('download', Permit::factory()->draft()->create()))->toBeFalse();
});

it('keeps applications of other organizations out of reach', function () {
    $other = Permit::factory()->draft()->create();

    $this->actingAs($this->registrar)
        ->get(route('admin.applicants.permits.show', [$this->applicant, $other]))
        ->assertNotFound();

    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.permits.submit', [$this->applicant, $other]))
        ->assertNotFound();
});

it('works only for applicants with an organization', function () {
    $this->actingAs($this->registrar)->get(route('admin.applicants.show', staffUser(Role::Cashier)))->assertNotFound();
    $this->actingAs($this->registrar)->get(route('admin.applicants.show', userWithRole(Role::Applicant)))->assertNotFound();
});

it('is for registrars and admins only', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))
        ->get(route('admin.applicants.show', $this->applicant))
        ->assertStatus($status);

    $this->actingAs(staffUser($role))
        ->post(route('admin.applicants.permits.store', $this->applicant), permitPayload($this->applicant))
        ->assertStatus($status === 200 ? 302 : $status);
})->with([
    'registrar' => [Role::Registrar, 200],
    'admin' => [Role::Admin, 200],
    'reviewer' => [Role::Reviewer, 403],
    'cashier' => [Role::Cashier, 403],
    'traffic officer' => [Role::TrafficOfficer, 403],
]);

it('keeps the applicant\'s own portal working on what the office started', function () {
    $this->actingAs($this->registrar)->post(route('admin.applicants.permits.store', $this->applicant), permitPayload($this->applicant));
    $permit = Permit::query()->sole();

    $this->actingAs($this->applicant)
        ->get(route('permits.show', $permit))
        ->assertOk();

    $this->actingAs($this->applicant)
        ->post(route('permits.submit', $permit))
        ->assertRedirect(route('permits.show', $permit));
});

it('never lets an applicant use the office routes', function () {
    $this->actingAs($this->applicant)
        ->get(route('admin.applicants.show', $this->applicant))
        ->assertForbidden();
});
