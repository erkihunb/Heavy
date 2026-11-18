<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A starting password an Admin hands over (on the create form, or as a
 * temporary password later): the person must choose their own at first
 * sign-in before anything else. Without one, a new person is e-mailed a link.
 */

const DEFAULT_PASSWORD = 'Welcome@2019';

function userOnDefaultPassword(Role $role = Role::Reviewer, bool $twoFactor = true): User
{
    $user = userWithRole($role, $twoFactor);
    $user->forceFill(['password' => DEFAULT_PASSWORD, 'must_change_password' => true])->save();

    return $user;
}

it('e-mails a new person a link to choose their password', function () {
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
