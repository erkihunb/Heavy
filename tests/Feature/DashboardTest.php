<?php

use App\Enums\Role;
use App\Models\User;
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
