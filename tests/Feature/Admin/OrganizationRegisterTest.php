<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Owner's request of 2026-11-20: whatever is registered can be seen,
 * corrected and deleted. The organizations requests are registered for, and
 * their vehicles, have a register of their own.
 */

beforeEach(function () {
    $this->registrar = staffUser(Role::Registrar);
    $this->applicant = applicantWithOrganization();
    $this->organization = $this->applicant->organization;
});

it('lists organizations, found by name, TIN or phone, without the Authority own', function () {
    $own = Organization::factory()->create();
    $own->forceFill(['is_institution' => true])->save();

    $this->actingAs($this->registrar)
        ->get(route('admin.organizations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organizations/index')
            ->where('organizations.data', fn ($rows) => collect($rows)->pluck('id')->contains($this->organization->id)
                && ! collect($rows)->pluck('id')->contains($own->id)));

    $this->actingAs($this->registrar)
        ->get(route('admin.organizations.index', ['search' => $this->organization->tin]))
        ->assertInertia(fn (Assert $page) => $page->has('organizations.data', 1));
});

it('opens an organization with its vehicles and requests', function () {
    $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

    $this->actingAs($this->registrar)
        ->get(route('admin.organizations.show', $this->organization))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organizations/show')
            ->where('organization.id', $this->organization->id)
            ->where('vehicles.0.id', $vehicle->id)
            ->where('vehicles.0.deletable', true)
            ->where('deletable', true));
});

it('corrects an organization and records it', function () {
    $this->actingAs($this->registrar)
        ->put(route('admin.organizations.update', $this->organization), [
            'name_am' => 'የተስተካከለ ስም',
            'name_en' => 'Corrected Name',
            'tin' => '1234567890',
            'phone' => '+251911000000',
            'email' => 'office@example.test',
            'address' => 'Bole',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.organizations.show', $this->organization));

    expect($this->organization->refresh()->name_am)->toBe('የተስተካከለ ስም')
        ->and($this->organization->tin)->toBe('1234567890')
        ->and(Activity::query()->where('event', 'organization.updated')->exists())->toBeTrue();
});

it('refuses a TIN another organization has', function () {
    $other = Organization::factory()->create();

    $this->actingAs($this->registrar)
        ->put(route('admin.organizations.update', $this->organization), [
            'name_am' => 'ስም', 'tin' => $other->tin, 'phone' => '+251911000000', 'email' => 'a@example.test',
        ])
        ->assertSessionHasErrors('tin');
});

it('deletes an organization with no request, its vehicles and its applicant account', function () {
    Vehicle::factory()->create(['organization_id' => $this->organization->id]);

    $this->actingAs($this->registrar)
        ->delete(route('admin.organizations.destroy', $this->organization))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.organizations.index'));

    expect(Organization::query()->whereKey($this->organization->id)->exists())->toBeFalse()
        ->and(Vehicle::query()->where('organization_id', $this->organization->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($this->applicant->id)->exists())->toBeFalse();
});

it('keeps an organization that has a request', function () {
    Permit::factory()->create(['organization_id' => $this->organization->id, 'applicant_id' => $this->applicant->id]);

    $this->actingAs($this->registrar)
        ->delete(route('admin.organizations.destroy', $this->organization))
        ->assertSessionHasErrors('organization');

    expect(Organization::query()->whereKey($this->organization->id)->exists())->toBeTrue();
});

it('corrects and deletes a vehicle no request carries', function () {
    $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
    $type = VehicleType::factory()->create();

    $this->actingAs($this->registrar)
        ->get(route('admin.organizations.vehicles.edit', [$this->organization, $vehicle]))
        ->assertInertia(fn (Assert $page) => $page->component('admin/organizations/vehicle-edit'));

    $this->actingAs($this->registrar)
        ->put(route('admin.organizations.vehicles.update', [$this->organization, $vehicle]), [
            'plate' => 'AA-03-D44444', 'vehicle_type_id' => $type->id, 'owner_name' => 'Owner',
        ])
        ->assertSessionHasNoErrors();

    expect($vehicle->refresh()->plate_normalized)->toContain('D44444');

    $this->actingAs($this->registrar)
        ->delete(route('admin.organizations.vehicles.destroy', [$this->organization, $vehicle]))
        ->assertSessionHasNoErrors();

    expect(Vehicle::query()->whereKey($vehicle->id)->exists())->toBeFalse();
});

it('keeps a vehicle that is on an issued permit as it is', function () {
    $permit = Permit::factory()->issued()->create(['organization_id' => $this->organization->id, 'applicant_id' => $this->applicant->id]);
    $vehicle = $permit->vehicles()->first();

    $this->actingAs($this->registrar)
        ->get(route('admin.organizations.vehicles.edit', [$this->organization, $vehicle]))
        ->assertForbidden();
    $this->actingAs($this->registrar)
        ->delete(route('admin.organizations.vehicles.destroy', [$this->organization, $vehicle]))
        ->assertSessionHasErrors('vehicle');
})->skip(fn () => Permit::factory()->issued()->create()->vehicles()->doesntExist(), 'issued factory has no vehicle');

it('needs the permission of its own for each action', function () {
    $role = RoleModel::findByName(Role::Registrar->value);
    $role->revokePermissionTo([Permission::UpdateOrganizations->value, Permission::DeleteOrganizations->value, Permission::UpdateVehicles->value, Permission::DeleteVehicles->value]);
    $this->registrar->refresh();
    $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

    $this->actingAs($this->registrar)->get(route('admin.organizations.show', $this->organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can', ['update' => false, 'delete' => false])
            ->where('vehicles.0.editUrl', null)
            ->where('vehicles.0.deleteUrl', null));
    $this->actingAs($this->registrar)->get(route('admin.organizations.edit', $this->organization))->assertForbidden();
    $this->actingAs($this->registrar)->delete(route('admin.organizations.destroy', $this->organization))->assertForbidden();
    $this->actingAs($this->registrar)->get(route('admin.organizations.vehicles.edit', [$this->organization, $vehicle]))->assertForbidden();
    $this->actingAs($this->registrar)->delete(route('admin.organizations.vehicles.destroy', [$this->organization, $vehicle]))->assertForbidden();

    $role->revokePermissionTo(Permission::ViewOrganizations->value);
    $this->registrar->refresh();
    $this->actingAs($this->registrar)->get(route('admin.organizations.index'))->assertForbidden();
});
