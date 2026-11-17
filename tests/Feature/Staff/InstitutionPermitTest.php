<?php

use App\Domain\Documents\PermitDocument;
use App\Domain\Institution\InstitutionFleet;
use App\Domain\Permits\PermitVerifier;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Authority's own vehicles are registered once and given their permits
 * directly by whoever holds institution-permits.issue: no application,
 * review, approval or payment; a number series and a paper of their own; the
 * same check on the road; and no place in the office's ordinary queues.
 */

beforeEach(function () {
    Notification::fake();
    $this->admin = staffUser(Role::Admin);
    $this->type = VehicleType::query()->active()->first() ?? VehicleType::factory()->create();
});

function registerInstitutionVehicle(User $by, string $plate): Vehicle
{
    test()->actingAs($by)
        ->post(route('staff.institution-vehicles.store'), ['plate' => $plate, 'vehicle_type_id' => test()->type->id, 'type_detail' => 'Grader'])
        ->assertSessionHasNoErrors();

    return InstitutionFleet::vehicles()->latest('created_at')->firstOrFail();
}

/** @return array<string, mixed> */
function institutionPermitInput(Vehicle ...$vehicles): array
{
    return [
        'vehicles' => array_map(fn (Vehicle $vehicle): string => $vehicle->id, $vehicles),
        'valid_from' => now(config('localization.display_timezone'))->toDateString(),
        'valid_to' => now(config('localization.display_timezone'))->addDays(30)->toDateString(),
        'reason' => 'Road maintenance works',
    ];
}

it('is only for people who may give institution permits', function () {
    $this->actingAs(staffUser(Role::Issuer))->get(route('staff.institution-permits.index'))->assertForbidden();
    $this->actingAs(staffUser(Role::Approver))->post(route('staff.institution-permits.store'), [])->assertForbidden();

    $role = RoleModel::query()->create(['name' => 'fleet', 'guard_name' => 'web', 'scope_type' => 'global']);
    $role->givePermissionTo(Permission::IssueInstitutionPermits->value);
    $fleet = User::factory()->withTwoFactor()->create();
    $fleet->assignRole($role);

    $this->actingAs($fleet)->get(route('staff.institution-permits.index'))->assertOk();
});

it('registers the institution\'s vehicles under the Authority itself', function () {
    $vehicle = registerInstitutionVehicle($this->admin, 'AA-3-B12345');

    expect($vehicle->organization->is_institution)->toBeTrue()
        ->and($vehicle->organization->name_am)->toBe(__('common.authority', [], 'am'));

    // The same plate is not registered twice, and a bad plate is refused.
    $this->actingAs($this->admin)
        ->post(route('staff.institution-vehicles.store'), ['plate' => 'AA-3-B12345', 'vehicle_type_id' => $this->type->id])
        ->assertSessionHasErrors('plate');
});

it('gives a permit directly: in force at once, free, numbered apart, with no payment or review', function () {
    $vehicle = registerInstitutionVehicle($this->admin, 'AA-3-B12345');

    $this->actingAs($this->admin)
        ->post(route('staff.institution-permits.store'), [...institutionPermitInput($vehicle), 'route' => 'Megenagna to Kality'])
        ->assertRedirect(route('staff.institution-permits.index'))
        ->assertSessionHas('success');

    $permit = Permit::query()->where('is_institutional', true)->firstOrFail();

    expect($permit->status)->toBe(PermitStatus::Issued)
        ->and($permit->permit_number)->toStartWith('INS-')->toEndWith('-0001')
        ->and($permit->fee_cents)->toBeNull()
        ->and($permit->issued_by)->toBe($this->admin->id)
        ->and($permit->route)->toBe('Megenagna to Kality')
        ->and($permit->verification_token_hash)->not->toBeNull()
        ->and($permit->vehicles->pluck('id')->all())->toBe([$vehicle->id])
        ->and(Payment::query()->count())->toBe(0)
        ->and($permit->transitions()->count())->toBe(1);

    // The paper is its own: the institution title and band, "free", and no receipt line.
    $html = PermitDocument::html($permit->refresh(), null, 'en');
    expect($html)->toContain('Movement permit for an institution vehicle')
        ->toContain('FREE OF CHARGE')
        ->toContain('Megenagna to Kality')
        ->not->toContain('Payment receipt number');

    // The next one continues the institution series; the paid permits' counter is untouched.
    $second = registerInstitutionVehicle($this->admin, 'AA-3-C54321');
    $this->actingAs($this->admin)->post(route('staff.institution-permits.store'), institutionPermitInput($second))->assertSessionHasNoErrors();
    expect(Permit::query()->where('is_institutional', true)->orderByDesc('permit_number')->value('permit_number'))->toEndWith('-0002');
});

it('gives several vehicles a permit each, or none when one cannot have it', function () {
    $first = registerInstitutionVehicle($this->admin, 'AA-3-B11111');
    $second = registerInstitutionVehicle($this->admin, 'AA-3-B22222');

    $this->actingAs($this->admin)->post(route('staff.institution-permits.store'), institutionPermitInput($first))->assertSessionHasNoErrors();

    // The first already holds one in these days: neither gets a new permit.
    $this->actingAs($this->admin)
        ->post(route('staff.institution-permits.store'), institutionPermitInput($second, $first))
        ->assertSessionHasErrors('vehicles');

    expect(Permit::query()->where('is_institutional', true)->count())->toBe(1);
});

it('refuses other people\'s vehicles, past days and periods longer than a year', function () {
    $vehicle = registerInstitutionVehicle($this->admin, 'AA-3-B12345');
    $foreign = Vehicle::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('staff.institution-permits.store'), institutionPermitInput($foreign))
        ->assertSessionHasErrors('vehicles');

    $this->actingAs($this->admin)
        ->post(route('staff.institution-permits.store'), [...institutionPermitInput($vehicle), 'valid_from' => now()->subDays(3)->toDateString()])
        ->assertSessionHasErrors('valid_from');

    $this->actingAs($this->admin)
        ->post(route('staff.institution-permits.store'), [...institutionPermitInput($vehicle), 'valid_to' => now()->addDays(500)->toDateString()])
        ->assertSessionHasErrors('valid_to');

    expect(Permit::query()->count())->toBe(0);
});

it('passes the check on the road, and fails it once revoked', function () {
    $vehicle = registerInstitutionVehicle($this->admin, 'AA-3-B12345');
    $this->actingAs($this->admin)->post(route('staff.institution-permits.store'), institutionPermitInput($vehicle));
    $permit = Permit::query()->where('is_institutional', true)->firstOrFail();

    $verifier = app(PermitVerifier::class);
    expect($verifier->byNumberAndPlate((string) $permit->permit_number, 'AA-3-B12345')?->id)->toBe($permit->id);

    $this->actingAs($this->admin)
        ->post(route('staff.institution-permits.revoke', $permit), ['reason' => 'Vehicle sold'])
        ->assertSessionHas('success');

    expect($permit->refresh()->status)->toBe(PermitStatus::Revoked)
        ->and($permit->revocation_reason)->toBe('Vehicle sold');

    // The vehicle is free again for a new permit.
    $this->actingAs($this->admin)->post(route('staff.institution-permits.store'), institutionPermitInput($vehicle))->assertSessionHasNoErrors();
});

it('keeps institution permits out of the office\'s ordinary lists', function () {
    $vehicle = registerInstitutionVehicle($this->admin, 'AA-3-B12345');
    $this->actingAs($this->admin)->post(route('staff.institution-permits.store'), institutionPermitInput($vehicle));

    expect(Permit::query()->ordinary()->count())->toBe(0);

    $this->actingAs($this->admin)
        ->get(route('staff.institution-permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/institution-permits/index')
            ->has('permits.data', 1)
            ->where('permits.data.0.in_force', true)
            ->where('permits.data.0.plates', [$vehicle->plate_normalized])
            ->where('vehicleCount', 1));

    $this->actingAs($this->admin)
        ->get(route('staff.permits.index'))
        ->assertInertia(fn (Assert $page) => $page->where('permits.data', []));
});

it('lists, changes and removes the institution\'s vehicles on their own page', function () {
    $vehicle = registerInstitutionVehicle($this->admin, 'AA-3-B12345');

    $this->actingAs($this->admin)
        ->get(route('staff.institution-vehicles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/institution-vehicles/index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.plate', $vehicle->plate_normalized)
            ->where('vehicles.data.0.permit', null)
            ->where('vehicles.data.0.deletable', true));

    $this->actingAs($this->admin)->get(route('staff.institution-vehicles.create'))->assertOk();
    $this->actingAs($this->admin)
        ->get(route('staff.institution-vehicles.edit', $vehicle))
        ->assertInertia(fn (Assert $page) => $page->component('staff/institution-vehicles/form')->where('vehicle.plate_locked', false));

    // Change the plate and the model.
    $this->actingAs($this->admin)
        ->put(route('staff.institution-vehicles.update', $vehicle), ['plate' => 'AA-3-B99999', 'vehicle_type_id' => $this->type->id, 'type_detail' => 'Loader'])
        ->assertRedirect(route('staff.institution-vehicles.index'))
        ->assertSessionHasNoErrors();

    expect($vehicle->refresh()->type_detail)->toBe('Loader')
        ->and($vehicle->plate_normalized)->not->toBe('')
        ->and($vehicle->plate_raw)->toBe('AA-3-B99999');

    // Remove it: it never held a permit.
    $this->actingAs($this->admin)->delete(route('staff.institution-vehicles.destroy', $vehicle))->assertSessionHas('success');
    expect(Vehicle::query()->whereKey($vehicle->id)->exists())->toBeFalse();
});

it('keeps a vehicle that has held a permit, and its plate while the permit is in force', function () {
    $vehicle = registerInstitutionVehicle($this->admin, 'AA-3-B12345');
    $this->actingAs($this->admin)->post(route('staff.institution-permits.store'), institutionPermitInput($vehicle))->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->put(route('staff.institution-vehicles.update', $vehicle), ['plate' => 'AA-3-B99999', 'vehicle_type_id' => $this->type->id])
        ->assertSessionHasErrors(['plate' => __('institution.errors.plate_locked')]);

    // The model can still be corrected.
    $this->actingAs($this->admin)
        ->put(route('staff.institution-vehicles.update', $vehicle), ['plate' => $vehicle->plate_raw, 'vehicle_type_id' => $this->type->id, 'type_detail' => 'Grader 140'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->delete(route('staff.institution-vehicles.destroy', $vehicle))
        ->assertSessionHasErrors('vehicle');

    expect($vehicle->refresh()->type_detail)->toBe('Grader 140');

    $this->actingAs($this->admin)
        ->get(route('staff.institution-vehicles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehicles.data.0.deletable', false)
            ->where('vehicles.data.0.permit.number', fn ($number) => str_starts_with($number, 'INS-')));
});

it('never touches another organization\'s vehicle from the institution pages', function () {
    registerInstitutionVehicle($this->admin, 'AA-3-B12345');
    $foreign = Vehicle::factory()->create();

    $this->actingAs($this->admin)->get(route('staff.institution-vehicles.edit', $foreign))->assertNotFound();
    $this->actingAs($this->admin)->put(route('staff.institution-vehicles.update', $foreign), ['plate' => 'AA-3-B77777', 'vehicle_type_id' => $this->type->id])->assertNotFound();
    $this->actingAs($this->admin)->delete(route('staff.institution-vehicles.destroy', $foreign))->assertNotFound();
    $this->actingAs(staffUser(Role::Issuer))->get(route('staff.institution-vehicles.index'))->assertForbidden();
});
