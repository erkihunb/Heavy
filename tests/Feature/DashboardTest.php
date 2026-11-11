<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('unverified users are sent to verify their email address first', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('the dashboard reports the account status of the signed-in user', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Applicant->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('account', ['emailVerified' => true, 'twoFactorEnabled' => false])
            ->where('auth.user.roles', ['applicant']));
});

test('the dashboard reflects confirmed two-factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('account.twoFactorEnabled', true));
});

test('the dashboard works for an account that has no role yet', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.roles', []));
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

test('an applicant without an organization is told to create one', function () {
    $this->actingAs(userWithRole(Role::Applicant))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('registration', ['hasOrganization' => false, 'vehicleCount' => 0]));
});

test('an applicant sees how many vehicles their own organization has registered', function () {
    $user = applicantWithOrganization();
    Vehicle::factory()->for($user->organization)->count(2)->create();
    Vehicle::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('registration', ['hasOrganization' => true, 'vehicleCount' => 2]));
});

test('staff see no registration step', function () {
    $this->actingAs(userWithRole(Role::Reviewer, twoFactor: true))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('registration', null));
});
