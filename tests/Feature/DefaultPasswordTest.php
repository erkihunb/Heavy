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

it('does not sign anyone in on the default password: they are sent to choose their own', function () {
    $user = userOnDefaultPassword(twoFactor: false);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => DEFAULT_PASSWORD])
        ->assertRedirect(route('first-password.show'));

    $this->assertGuest();

    $this->get(route('first-password.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/first-password')
            ->where('email', $user->email));
});

it('still refuses a wrong password', function () {
    $user = userOnDefaultPassword();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'not-the-default'])
        ->assertSessionHasErrors('email');

    $this->get(route('first-password.show'))->assertRedirect(route('login'));
});

it('shows the page to nobody who did not just give the default password', function () {
    $this->get(route('first-password.show'))->assertRedirect(route('login'));

    $this->post(route('first-password.store'), ['password' => 'my-own-Passw0rd!', 'password_confirmation' => 'my-own-Passw0rd!'])
        ->assertRedirect(route('login'));
});

it('saves the chosen password and sends the person to sign in with it', function () {
    $user = userOnDefaultPassword(twoFactor: false);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => DEFAULT_PASSWORD]);

    $this->post(route('first-password.store'), ['password' => 'my-own-Passw0rd!', 'password_confirmation' => 'my-own-Passw0rd!'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('auth_ui.first_password.done'));

    $this->assertGuest();

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('my-own-Passw0rd!', $user->password))->toBeTrue();

    // The page is closed again, and the new password signs them in.
    $this->get(route('first-password.show'))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'my-own-Passw0rd!']);
    $this->assertAuthenticatedAs($user);
});

it('refuses the default password as the new password', function () {
    withDefaultPassword();
    $user = userOnDefaultPassword();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => DEFAULT_PASSWORD]);

    $this->from(route('first-password.show'))
        ->post(route('first-password.store'), ['password' => DEFAULT_PASSWORD, 'password_confirmation' => DEFAULT_PASSWORD])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

it('needs the new password typed twice', function () {
    $user = userOnDefaultPassword();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => DEFAULT_PASSWORD]);

    $this->from(route('first-password.show'))
        ->post(route('first-password.store'), ['password' => 'my-own-Passw0rd!', 'password_confirmation' => 'something-else'])
        ->assertSessionHasErrors('password');
});

it('closes the page after a while', function () {
    $user = userOnDefaultPassword();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => DEFAULT_PASSWORD]);

    $this->travel(16)->minutes();

    $this->get(route('first-password.show'))->assertRedirect(route('login'));
});

it('ends a session that is signed in on the default password all the same', function (string $route) {
    $this->actingAs(userOnDefaultPassword())
        ->get(route($route))
        ->assertRedirect(route('first-password.show'));

    $this->assertGuest();
})->with(['dashboard', 'profile.edit', 'user-password.edit', 'two-factor.show']);

it('blocks write requests on the default password', function () {
    $user = userOnDefaultPassword();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email])
        ->assertRedirect(route('first-password.show'));

    expect($user->refresh()->name)->not->toBe('Changed');
});

it('answers JSON requests on the default password with 403', function () {
    $this->actingAs(userOnDefaultPassword())
        ->getJson(route('dashboard'))
        ->assertForbidden();
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
