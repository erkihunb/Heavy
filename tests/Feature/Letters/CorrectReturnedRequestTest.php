<?php

use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\ValidityRules;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\Role as RoleModel;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Owner's request of 2026-11-19: a request returned for changes must be
 * correctable by the Registrar, vehicles included, and every action (create,
 * correct, delete) has a permission of its own.
 */

beforeEach(function () {
    Notification::fake();
    withFeeSchedule();
    $this->registrar = staffUser(Role::Registrar);
    $this->type = VehicleType::factory()->create();
    $this->otherType = VehicleType::factory()->create();
    $this->letter = registeredLetter();
    $this->permits = $this->letter->permits()->with('vehicles')->orderBy('id')->get();
    $this->approver = staffUser(Role::Approver);

    $this->actingAs($this->approver)
        ->post(route('letters.return', $this->letter), [
            'permits' => $this->permits->pluck('id')->all(),
            'note' => 'Fix the plate.',
        ])
        ->assertSessionHasNoErrors();

    $this->permit = $this->permits[0]->refresh();
    $this->vehicle = $this->permit->vehicles()->wherePivot('role', 'primary')->sole();
});

it('opens a returned vehicle of a letter for correction, its scan kept', function () {
    $this->actingAs($this->registrar)
        ->get(route('admin.permits.edit', $this->permit))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/edit')
            ->where('hasStoredScan', true));

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.show', $this->permit))
        ->assertInertia(fn (Assert $page) => $page->where('actions', ['update' => true, 'submit' => true, 'delete' => true]));
});

it('corrects the plate, type and owner of a returned vehicle', function () {
    $this->actingAs($this->registrar)
        ->get(route('admin.permits.vehicles.edit', [$this->permit, $this->vehicle]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/vehicle-edit')
            ->where('vehicle.id', $this->vehicle->id));

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.vehicles.update', [$this->permit, $this->vehicle]), [
            'plate' => 'AA-03-B77777',
            'vehicle_type_id' => $this->otherType->id,
            'owner_name' => 'Corrected Owner',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.permits.edit', $this->permit));

    $vehicle = $this->vehicle->refresh();
    expect($vehicle->plate_normalized)->toContain('B77777')
        ->and($vehicle->vehicle_type_id)->toBe($this->otherType->id)
        ->and($vehicle->owner_name)->toBe('Corrected Owner');
});

it('keeps a vehicle as it is once another request carrying it has gone further', function () {
    // Another vehicle of the same letter, approved: its permit has gone further.
    $other = $this->permits[1]->refresh();
    $other->vehicles()->attach($this->vehicle->id, [
        'role' => 'trailer',
        'period_from' => $other->valid_from,
        'period_to' => $other->valid_to,
    ]);
    $other->forceFill(['status' => PermitStatus::AwaitingPayment])->save();

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.vehicles.edit', [$this->permit, $this->vehicle]))
        ->assertForbidden();

});

it('refuses a vehicle that is not on the request', function () {
    $other = Vehicle::factory()->create(['organization_id' => $this->permit->organization_id]);

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.vehicles.edit', [$this->permit, $other]))
        ->assertNotFound();
});

it('deletes a returned request, keeping it in the record as withdrawn', function () {
    $this->actingAs($this->registrar)
        ->delete(route('admin.permits.destroy', $this->permit))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.permits.index'));

    expect($this->permit->refresh()->status)->toBe(PermitStatus::Withdrawn)
        ->and(Permit::query()->whereKey($this->permit->id)->exists())->toBeTrue();
});

it('needs the permission of its own for each action', function () {
    $role = RoleModel::findByName(Role::Registrar->value);
    $role->revokePermissionTo(Permission::UpdateRegisteredPermits->value);
    $role->revokePermissionTo(Permission::DeleteRegisteredPermits->value);
    $this->registrar->refresh();

    $this->actingAs($this->registrar)->get(route('admin.permits.edit', $this->permit))->assertForbidden();
    $this->actingAs($this->registrar)->get(route('admin.permits.vehicles.edit', [$this->permit, $this->vehicle]))->assertForbidden();
    $this->actingAs($this->registrar)->delete(route('admin.permits.destroy', $this->permit))->assertForbidden();
    $this->actingAs($this->registrar)
        ->get(route('admin.permits.show', $this->permit))
        ->assertInertia(fn (Assert $page) => $page->where('actions', ['update' => false, 'submit' => false, 'delete' => false]));

    // Correcting alone, without registering: the returned request still opens and may be corrected.
    $role->givePermissionTo(Permission::UpdateRegisteredPermits->value);
    $role->revokePermissionTo(Permission::RegisterApplicants->value);
    $this->registrar->refresh();

    $this->actingAs($this->registrar)->get(route('admin.permits.edit', $this->permit))->assertOk();
    $this->actingAs($this->registrar)->delete(route('admin.permits.destroy', $this->permit))->assertForbidden();
});

it('offers the correction on the review page to whoever may correct it', function () {
    $admin = staffUser(Role::Admin);

    $this->actingAs($admin)
        ->get(route('staff.permits.show', $this->permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('correction.edit', route('admin.permits.edit', $this->permit))
            ->where('correction.open', route('admin.permits.show', $this->permit))
            ->where('correction.delete', true));

    $this->actingAs($this->approver)
        ->get(route('staff.permits.show', $this->permit))
        ->assertInertia(fn (Assert $page) => $page->where('correction', null));
});

it('fills the edit page as the create form, with the request as registered', function () {
    $this->actingAs($this->registrar)
        ->get(route('admin.permits.edit', $this->permit))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/edit')
            ->where('intake.plate', $this->vehicle->plate_raw)
            ->where('intake.vehicle_type_id', (string) $this->vehicle->vehicle_type_id)
            ->where('intake.owner_name', $this->vehicle->owner_name)
            ->has('organization.name')
            ->where('hasStoredScan', true));
});

/**
 * The form as the edit page sends it: the permit as it is, the vehicle typed.
 *
 * @param  list<array<string, mixed>>  $vehicles
 * @return array<string, mixed>
 */
function correctionPayload(Permit $permit, array $vehicles): array
{
    $rate = app(FeeCalculator::class)->rates($permit->permit_type, (int) $vehicles[0]['vehicle_type_id'], ValidityRules::today())[0];

    return [
        'permit_type' => $permit->permit_type->value,
        'reason' => 'Corrected reason',
        'route' => '',
        'fee_schedule_id' => $rate['id'],
        'valid_from' => ValidityRules::today()->addDays(3)->toDateString(),
        'any_time' => true,
        'letter_number' => $permit->letter_number,
        'letter_date' => $permit->letter_date?->toDateString(),
        'vehicles' => $vehicles,
    ];
}

it('saves a typed plate on the request own vehicle, fixing it in place', function () {
    $count = Vehicle::query()->count();

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $this->permit), correctionPayload($this->permit, [[
            'plate' => 'AA-03-B12345',
            'vehicle_type_id' => $this->otherType->id,
            'owner_name' => 'New Owner',
        ]]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.permits.show', $this->permit));

    $permit = $this->permit->refresh()->load('vehicles');
    $vehicle = $this->vehicle->refresh();

    expect(Vehicle::query()->count())->toBe($count)
        ->and($permit->reason)->toBe('Corrected reason')
        ->and($permit->vehicles->pluck('id')->all())->toBe([$vehicle->id])
        ->and($vehicle->plate_normalized)->toContain('B12345')
        ->and($vehicle->vehicle_type_id)->toBe($this->otherType->id)
        ->and($vehicle->owner_name)->toBe('New Owner');
});

it('registers a new vehicle when the old one is on a request that went further', function () {
    $other = $this->permits[1]->refresh();
    $other->vehicles()->attach($this->vehicle->id, ['role' => 'trailer', 'period_from' => $other->valid_from, 'period_to' => $other->valid_to]);
    $other->forceFill(['status' => PermitStatus::AwaitingPayment])->save();
    $oldPlate = $this->vehicle->plate_normalized;

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $this->permit), correctionPayload($this->permit, [[
            'plate' => 'AA-03-C55555',
            'vehicle_type_id' => $this->vehicle->vehicle_type_id,
            'owner_name' => '',
        ]]))
        ->assertSessionHasNoErrors();

    $primary = $this->permit->refresh()->vehicles()->wherePivot('role', 'primary')->sole();

    expect($primary->id)->not->toBe($this->vehicle->id)
        ->and($primary->plate_normalized)->toContain('C55555')
        ->and($this->vehicle->refresh()->plate_normalized)->toBe($oldPlate);
});

it('refuses a typed plate that is not well formed', function () {
    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $this->permit), correctionPayload($this->permit, [[
            'plate' => 'nonsense',
            'vehicle_type_id' => $this->vehicle->vehicle_type_id,
        ]]))
        ->assertSessionHasErrors('vehicles.0.plate');
});
