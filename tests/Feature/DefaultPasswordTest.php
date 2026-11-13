<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The default password: when one is set in System settings › Security, a new
 * staff account starts with it instead of an e-mailed link, and the person
 * must choose their own password at first sign-in before anything else.
 */

const DEFAULT_PASSWORD = 'Welcome@2019';

function withDefaultPassword(): void
{
    config(['permits.security.default_password' => DEFAULT_PASSWORD]);
}

function userOnDefaultPassword(Role $role = Role::Reviewer, bool $twoFactor = true): User
{
    $user = userWithRole($role, $twoFactor);
    $user->forceFill(['password' => DEFAULT_PASSWORD, 'must_change_password' => true])->save();

    return $user;
}

it('has no default password until one is set', function () {
    expect(config('permits.security.default_password'))->toBeNull();
});

it('opens a staff account with the default password and sends no link', function () {
    Notification::fake();
    withDefaultPassword();

    $this->actingAs(staffUser(Role::Admin))
        ->post(route('admin.users.store'), ['name' => 'Demo Cashier', 'email' => 'demo.cashier@example.test', 'roles' => ['cashier'], 'branch_id' => null])
        ->assertSessionHas('success', __('admin.users.messages.created_default_password'));

    $user = User::query()->where('email', 'demo.cashier@example.test')->sole();

    expect(Hash::check(DEFAULT_PASSWORD, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();

    Notification::assertNothingSent();
});

it('keeps the e-mailed link when no default password is set', function () {
    Notification::fake();

    $this->actingAs(staffUser(Role::Admin))
        ->post(route('admin.users.store'), ['name' => 'Demo Cashier', 'email' => 'demo.cashier@example.test', 'roles' => ['cashier'], 'branch_id' => null])
        ->assertSessionHas('success', __('admin.users.messages.created'));

    $user = User::query()->where('email', 'demo.cashier@example.test')->sole();

    expect($user->must_change_password)->toBeFalse();
    Notification::assertSentTo($user, StaffAccountCreated::class);
});

it('lets the person sign in with the default password', function () {
    // A new account has no two-factor authentication yet, so there is no code to ask for.
    $user = userOnDefaultPassword(twoFactor: false);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => DEFAULT_PASSWORD]);

    $this->assertAuthenticatedAs($user);
});

it('sends someone on the default password to choose their own', function (string $route) {
    $this->actingAs(userOnDefaultPassword())
        ->get(route($route))
        ->assertRedirect(route('user-password.edit'));
})->with(['home', 'dashboard', 'profile.edit', 'two-factor.show']);

it('asks for the new password before two-factor authentication', function () {
    $this->actingAs(userOnDefaultPassword(twoFactor: false))
        ->get(route('dashboard'))
        ->assertRedirect(route('user-password.edit'));
});

it('blocks write requests until the password is changed', function () {
    $user = userOnDefaultPassword();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email])
        ->assertRedirect(route('user-password.edit'));

    expect($user->refresh()->name)->not->toBe('Changed');
});

it('answers JSON requests with 403 until the password is changed', function () {
    $this->actingAs(userOnDefaultPassword())
        ->getJson(route('dashboard'))
        ->assertForbidden();
});

it('shows the password screen with a notice that the change is required', function () {
    $this->actingAs(userOnDefaultPassword(twoFactor: false))
        ->get(route('user-password.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/password')
            ->where('auth.mustChangePassword', true));
});

it('does not show the notice to anyone else', function () {
    $this->actingAs(staffUser(Role::Reviewer))
        ->get(route('user-password.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.mustChangePassword', false));
});

it('still lets the person sign out and switch language', function () {
    $user = userOnDefaultPassword();

    $this->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('user-password.edit'));

    expect($user->refresh()->locale)->toBe('en');

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
});

it('lets the person in once they have chosen their own password', function () {
    $user = userOnDefaultPassword();

    $this->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => DEFAULT_PASSWORD,
            'password' => 'my-own-Passw0rd!',
            'password_confirmation' => 'my-own-Passw0rd!',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('my-own-Passw0rd!', $user->password))->toBeTrue();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('refuses the default password as the new password', function () {
    withDefaultPassword();
    $user = userOnDefaultPassword();

    $this->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => DEFAULT_PASSWORD,
            'password' => DEFAULT_PASSWORD,
            'password_confirmation' => DEFAULT_PASSWORD,
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

it('counts a password chosen through a reset link as the change', function () {
    $user = userOnDefaultPassword();

    $this->post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'my-own-Passw0rd!',
        'password_confirmation' => 'my-own-Passw0rd!',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->must_change_password)->toBeFalse();
});

it('lets an administrator set the default password, kept as a secret', function () {
    $admin = staffUser(Role::Admin);

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'security'), settingsForm('security', ['permits.security.default_password' => DEFAULT_PASSWORD]))
        ->assertSessionHasNoErrors();

    expect(config('permits.security.default_password'))->toBe(DEFAULT_PASSWORD);

    $this->actingAs($admin)
        ->get(route('admin.settings.show', 'security'))
        ->assertOk()
        ->assertDontSee(DEFAULT_PASSWORD);
});

it('refuses a default password that is too short', function () {
    $this->actingAs(staffUser(Role::Admin))
        ->put(route('admin.settings.update', 'security'), settingsForm('security', ['permits.security.default_password' => 'abc']))
        ->assertSessionHasErrors('permits__security__default_password');
});
