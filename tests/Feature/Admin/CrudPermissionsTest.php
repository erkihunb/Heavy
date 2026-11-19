<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\FeeSchedule;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Owner's request of 2026-11-20: every part of the system can be seen, added,
 * changed and deleted, and each of these is a permission of its own.
 */

/**
 * A member of staff holding exactly these permissions.
 *
 * @param  list<Permission>  $permissions
 */
function holding(array $permissions): User
{
    $role = RoleModel::create(['name' => 'crud-'.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(array_map(fn (Permission $permission): string => $permission->value, [Permission::ViewDashboard, ...$permissions]));
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role);

    return $user;
}

it('deletes a fee rate, and records it', function () {
    $line = FeeSchedule::factory()->create();

    $this->actingAs(staffUser(Role::Admin))
        ->delete(route('admin.fee-schedules.destroy', $line))
        ->assertRedirect(route('admin.fee-schedules.index'));

    expect(FeeSchedule::query()->whereKey($line->id)->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'fee_schedule.deleted')->exists())->toBeTrue();
});

it('deletes a vehicle type nothing uses, and keeps one in use', function () {
    $unused = VehicleType::factory()->create();
    $used = VehicleType::factory()->create();
    Vehicle::factory()->create(['vehicle_type_id' => $used->id]);
    $admin = staffUser(Role::Admin);

    $this->actingAs($admin)->delete(route('admin.vehicle-types.destroy', $unused))->assertSessionHasNoErrors();
    $this->actingAs($admin)->delete(route('admin.vehicle-types.destroy', $used))->assertSessionHasErrors('vehicle_type');

    expect(VehicleType::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(VehicleType::query()->whereKey($used->id)->exists())->toBeTrue();
});

it('deletes a branch with no staff and no permits, and keeps one in use', function () {
    $empty = Branch::factory()->create();
    $busy = Branch::factory()->create();
    User::factory()->create(['branch_id' => $busy->id]);
    $admin = staffUser(Role::Admin);

    $this->actingAs($admin)->delete(route('admin.branches.destroy', $empty))->assertSessionHasNoErrors();
    $this->actingAs($admin)->delete(route('admin.branches.destroy', $busy))->assertSessionHasErrors('branch');

    expect(Branch::query()->whereKey($empty->id)->exists())->toBeFalse()
        ->and(Branch::query()->whereKey($busy->id)->exists())->toBeTrue();
});

it('lets each action through only with its own permission', function (string $view, Permission $see, Permission $add, Permission $change, Permission $remove, Closure $make, string $param) {
    $record = $make();
    $viewer = holding([$see]);

    $this->actingAs($viewer)->get(route("admin.{$view}.index"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can', ['create' => false, 'update' => false, 'delete' => false]));
    $this->actingAs($viewer)->get(route("admin.{$view}.create"))->assertForbidden();
    $this->actingAs($viewer)->get(route("admin.{$view}.edit", [$param => $record]))->assertForbidden();
    $this->actingAs($viewer)->delete(route("admin.{$view}.destroy", [$param => $record]))->assertForbidden();

    $this->actingAs(holding([$see, $add]))->get(route("admin.{$view}.create"))->assertOk();
    $this->actingAs(holding([$see, $change]))->get(route("admin.{$view}.edit", [$param => $record]))->assertOk();
    $this->actingAs(holding([$see, $remove]))
        ->get(route("admin.{$view}.index"))
        ->assertInertia(fn (Assert $page) => $page->where('can.delete', true)->where('can.update', false));

    $this->actingAs(holding([$add]))->get(route("admin.{$view}.index"))->assertForbidden();
})->with([
    'fee rates' => ['fee-schedules', Permission::ViewFeeSchedules, Permission::CreateFeeSchedules, Permission::UpdateFeeSchedules, Permission::DeleteFeeSchedules, fn () => FeeSchedule::factory()->create(), 'fee_schedule'],
    'vehicle types' => ['vehicle-types', Permission::ViewVehicleTypes, Permission::CreateVehicleTypes, Permission::UpdateVehicleTypes, Permission::DeleteVehicleTypes, fn () => VehicleType::factory()->create(), 'vehicle_type'],
    'branches' => ['branches', Permission::ViewBranches, Permission::CreateBranches, Permission::UpdateBranches, Permission::DeleteBranches, fn () => Branch::factory()->create(), 'branch'],
]);

it('shows the settings read only without settings.update', function () {
    $viewer = holding([Permission::ViewSettings]);

    $this->actingAs($viewer)
        ->get(route('admin.settings.show', 'general'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canUpdate', false));
    $this->actingAs($viewer)->put(route('admin.settings.update', 'general'), [])->assertForbidden();
});
