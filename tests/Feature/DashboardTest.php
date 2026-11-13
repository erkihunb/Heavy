<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('people who hold the dashboard permission can visit it', function () {
    $this->actingAs(staffUser(Role::Registrar))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

test('without the dashboard permission a person starts on the first page they may open', function () {
    // No role at all: only their own profile is theirs.
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('profile.edit'));

    // A role an administrator took the dashboard from: straight to its work.
    RoleModel::findByName(Role::Reviewer->value)->revokePermissionTo(Permission::ViewDashboard->value);

    $this->actingAs(staffUser(Role::Reviewer))
        ->get(route('dashboard'))
        ->assertRedirect(route('staff.permits.index'));
});

test('the dashboard permission alone opens the dashboard, whatever the role is called', function () {
    $role = RoleModel::query()->create(['name' => 'onlooker', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::ViewDashboard->value);
    $user = User::factory()->create();
    $user->assignRole('onlooker');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            // Nothing of the office's work: no figures, and not asked for two-factor as staff are.
            ->where('counts', [])
            ->where('account.staff', false)
            ->where('activity', null));
});

test('unverified users are sent to verify their email address first', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('the dashboard reports the account status of the signed-in user', function () {
    $this->actingAs(staffUser(Role::Reviewer))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('account', ['emailVerified' => true, 'twoFactorEnabled' => true, 'staff' => true])
            ->where('auth.user.roles', ['reviewer']));
});

test('the dashboard reflects confirmed two-factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('account.twoFactorEnabled', true));
});

test('every role has a name and a summary in both languages', function () {
    foreach (['am', 'en'] as $locale) {
        $roles = require lang_path("{$locale}/roles.php");

        foreach ([...array_map(fn (Role $role) => $role->value, Role::cases()), 'none'] as $role) {
            expect($roles[$role]['name'] ?? '')->not->toBe('', "{$locale}.roles.{$role}.name")
                ->and($roles[$role]['summary'] ?? '')->not->toBe('', "{$locale}.roles.{$role}.summary");
        }
    }
});
