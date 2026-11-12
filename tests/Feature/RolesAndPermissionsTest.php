<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

function permissionValues(Role $role): array
{
    $values = array_map(fn (Permission $permission) => $permission->value, $role->permissions());
    sort($values);

    return $values;
}

it('seeds every role and every permission', function () {
    expect(RoleModel::pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::cases())->map->value->sort()->values()->all())
        ->and(PermissionModel::pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::cases())->map->value->sort()->values()->all());
});

it('grants each role exactly the permissions mapped to it', function (Role $role) {
    $granted = RoleModel::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();

    expect($granted)->toBe(permissionValues($role));
})->with(Role::cases());

it('matches the role table in the specification', function (Role $role, array $expected) {
    sort($expected);

    expect(permissionValues($role))->toBe($expected);
})->with([
    'applicant' => [Role::Applicant, ['organization.manage-own', 'vehicles.manage-own', 'permits.apply', 'payments.submit', 'permits.download-own']],
    'reviewer' => [Role::Reviewer, ['permits.view-queue', 'permits.review', 'permits.approve']],
    'approver' => [Role::Approver, ['permits.view-queue', 'permits.approve', 'permits.revoke', 'reports.view']],
    'cashier' => [Role::Cashier, ['permits.view-queue', 'payments.verify', 'reports.view']],
    'traffic officer' => [Role::TrafficOfficer, ['verification.scan']],
    'registrar' => [Role::Registrar, ['applicants.register']],
    'issuer' => [Role::Issuer, ['permits.view-queue', 'permits.issue']],
    'supervisor' => [Role::Supervisor, ['permits.view-queue', 'permits.comment', 'reports.view']],
    'admin' => [Role::Admin, [
        'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.assign-roles',
        'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'permissions.view',
        'vehicle-types.manage', 'fee-schedules.manage', 'settings.manage', 'audit-log.view',
        'permits.view-queue', 'permits.review', 'permits.approve', 'permits.revoke', 'payments.verify', 'permits.issue',
        'permits.comment', 'verification.scan', 'reports.view', 'applicants.register',
    ]],
]);

it('assigns every permission to at least one role', function () {
    $assigned = collect(Role::cases())->flatMap(fn (Role $role) => permissionValues($role))->unique();

    expect($assigned->count())->toBe(count(Permission::cases()));
});

it('lets a user exercise only the permissions of their role', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))
            ->toBe(in_array($permission, $role->permissions(), true), "{$role->value} / {$permission->value}");
    }
})->with(Role::cases());

it('gives a user without a role no permissions at all', function () {
    $user = User::factory()->create();

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))->toBeFalse();
    }
});

it('gives admin every staff permission, but not those of an applicant', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission->value))
            ->toBe(! in_array($permission, Role::Applicant->permissions(), true), $permission->value);
    }
});

it('keeps approving and payment verification in different roles', function () {
    expect(Role::Approver->permissions())->not->toContain(Permission::VerifyPayments)
        ->and(Role::Cashier->permissions())->not->toContain(Permission::ApprovePermits);
});

it('is idempotent', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(RoleModel::count())->toBe(count(Role::cases()))
        ->and(PermissionModel::count())->toBe(count(Permission::cases()));
});

it('keeps what administrators changed on a role when it seeds again', function () {
    $cashier = RoleModel::findByName(Role::Cashier->value);
    $cashier->givePermissionTo(Permission::ApprovePermits->value);
    $cashier->revokePermissionTo(Permission::ViewReports->value);

    $this->seed(RolesAndPermissionsSeeder::class);

    $cashier = RoleModel::findByName(Role::Cashier->value);
    expect($cashier->hasPermissionTo(Permission::ApprovePermits->value))->toBeTrue()
        ->and($cashier->hasPermissionTo(Permission::ViewReports->value))->toBeFalse();
});

it('always gives the administrator role every staff permission', function () {
    RoleModel::findByName(Role::Admin->value)->revokePermissionTo(Permission::ManageSettings->value);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(RoleModel::findByName(Role::Admin->value)->hasPermissionTo(Permission::ManageSettings->value))->toBeTrue();
});

it('treats every role except applicant as staff', function () {
    expect(Role::staff())->toBe([Role::Reviewer, Role::Approver, Role::Cashier, Role::TrafficOfficer, Role::Registrar, Role::Issuer, Role::Supervisor, Role::Admin])
        ->and(Role::Applicant->isStaff())->toBeFalse();

    foreach (Role::staff() as $role) {
        expect($role->isStaff())->toBeTrue();
    }
});

it('reports whether a user is staff from their role', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    expect($user->isStaff())->toBe($role->isStaff());
})->with(Role::cases());

it('seeds one demo user per role', function () {
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUserSeeder::class);

    expect(User::count())->toBe(count(Role::cases()));

    foreach (Role::cases() as $role) {
        $user = User::where('email', "{$role->value}@example.test")->firstOrFail();

        expect($user->getRoleNames()->all())->toBe([$role->value]);
    }
});

it('refuses to seed demo users in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => $this->seed(DemoUserSeeder::class))->toThrow(LogicException::class);
    expect(User::count())->toBe(0);
});

it('does not create demo users outside the local environment', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(0)
        ->and(RoleModel::count())->toBe(count(Role::cases()));
});
