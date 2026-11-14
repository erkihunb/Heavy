<?php

use App\Enums\Role;
use Inertia\Testing\AssertableInertia as Assert;

function passwordConfirmed(): array
{
    return ['auth.password_confirmed_at' => time()];
}

dataset('staff roles', fn () => Role::staff());

it('sends staff without two-factor authentication to the setup screen', function (Role $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('dashboard'))
        ->assertRedirect(route('two-factor.show'));
})->with('staff roles');

it('blocks every other screen for staff without two-factor authentication', function (string $route) {
    $this->actingAs(userWithRole(Role::Admin))
        ->get(route($route))
        ->assertRedirect(route('two-factor.show'));
})->with(['home', 'dashboard', 'profile.edit', 'user-password.edit']);

it('blocks write requests for staff without two-factor authentication', function () {
    $user = userWithRole(Role::Approver);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Changed', 'email' => $user->email])
        ->assertRedirect(route('two-factor.show'));

    expect($user->refresh()->name)->not->toBe('Changed');
});

it('answers JSON requests from staff without two-factor authentication with 403', function () {
    $this->actingAs(userWithRole(Role::Cashier))
        ->getJson(route('dashboard'))
        ->assertForbidden();
});

it('lets staff with confirmed two-factor authentication in', function (Role $role) {
    $this->actingAs(userWithRole($role, twoFactor: true))
        ->get(route('dashboard'))
        ->assertOk();
})->with('staff roles');

it('treats unconfirmed two-factor setup as not enabled', function () {
    $user = userWithRole(Role::Reviewer, twoFactor: true);
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('two-factor.show'));
});

it('signs applicants out rather than asking them for two-factor authentication', function () {
    assertSignedOut($this->actingAs(userWithRole(Role::Applicant))->get(route('profile.edit')));
});

it('shows staff the setup screen with a notice that it is required', function () {
    $this->actingAs(userWithRole(Role::TrafficOfficer))
        ->withSession(passwordConfirmed())
        ->get(route('two-factor.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/two-factor')
            ->where('auth.mustEnableTwoFactor', true)
            ->where('twoFactorEnabled', false));
});

it('does not show the notice once two-factor authentication is enabled', function () {
    $this->actingAs(userWithRole(Role::Admin, twoFactor: true))
        ->withSession(passwordConfirmed())
        ->get(route('two-factor.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.mustEnableTwoFactor', false));
});

it('lets staff confirm their password on the way to the setup screen', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('password.confirm'))
        ->assertOk();
});

it('lets staff start enabling two-factor authentication', function () {
    $user = userWithRole(Role::Admin);

    $this->actingAs($user)
        ->withSession(passwordConfirmed())
        ->post(route('two-factor.enable'))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->two_factor_secret)->not->toBeNull();
});

it('lets staff without two-factor authentication sign out', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

it('lets staff without two-factor authentication switch language', function () {
    $user = userWithRole(Role::Admin);

    $this->actingAs($user)
        ->from(route('two-factor.show'))
        ->put(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('two-factor.show'));

    expect($user->refresh()->locale)->toBe('en');
});

it('can be switched off by configuration for local development', function () {
    config(['permits.staff_two_factor_required' => false]);

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('dashboard'))
        ->assertOk();
});

it('is required by default', function () {
    expect(config('permits.staff_two_factor_required'))->toBeTrue();
});

it('does not affect guests', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
    $this->get(route('login'))->assertOk();
});

it('lets an admin switch the requirement off and on in the system settings', function () {
    $admin = userWithRole(Role::Admin);
    $admin->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $other = userWithRole(Role::Approver);

    $this->actingAs($other)->get(route('dashboard'))->assertRedirect(route('two-factor.show'));

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'security'), settingsForm('security', ['permits.staff_two_factor_required' => false]))
        ->assertSessionHasNoErrors();

    expect(config('permits.staff_two_factor_required'))->toBeFalse();
    $this->actingAs($other)->get(route('dashboard'))->assertOk();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'security'), settingsForm('security', ['permits.staff_two_factor_required' => true]))
        ->assertSessionHasNoErrors();

    $this->actingAs($other)->get(route('dashboard'))->assertRedirect(route('two-factor.show'));
});
