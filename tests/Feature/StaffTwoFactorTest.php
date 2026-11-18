<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

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

it('is required of all staff by default', function () {
    expect(config('permits.security.mfa_enabled'))->toBeTrue()
        ->and(config('permits.security.mfa_required_for_all'))->toBeTrue()
        ->and(userWithRole(Role::Approver)->requiresTwoFactor())->toBeTrue();
});

it('does not affect guests', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
    $this->get(route('login'))->assertOk();
});

it('is required of nobody when two-factor is switched off', function () {
    config(['permits.security.mfa_enabled' => false]);
    $user = userWithRole(Role::Admin);
    $user->forceFill(['two_factor_requirement' => User::TWO_FACTOR_REQUIRED])->save();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('is required only of the chosen roles when not required of all staff', function () {
    $approver = userWithRole(Role::Approver);
    $cashier = userWithRole(Role::Cashier);
    config([
        'permits.security.mfa_required_for_all' => false,
        'permits.security.mfa_required_roles' => [RoleModel::findByName(Role::Approver->value)->id],
    ]);

    $this->actingAs($approver)->get(route('dashboard'))->assertRedirect(route('two-factor.show'));
    $this->actingAs($cashier)->get(route('dashboard'))->assertOk();
});

it('follows an admin\'s choice for one person over the policy', function () {
    $exempt = userWithRole(Role::Admin);
    $exempt->forceFill(['two_factor_requirement' => User::TWO_FACTOR_EXEMPT])->save();

    $this->actingAs($exempt)->get(route('dashboard'))->assertOk();

    config(['permits.security.mfa_required_for_all' => false]);
    $required = userWithRole(Role::Cashier);
    $required->forceFill(['two_factor_requirement' => User::TWO_FACTOR_REQUIRED])->save();

    $this->actingAs($required)->get(route('dashboard'))->assertRedirect(route('two-factor.show'));
});

it('lets an admin choose for one person, audited', function () {
    $admin = userWithRole(Role::Admin);
    $admin->forceFill(['two_factor_requirement' => User::TWO_FACTOR_EXEMPT])->save();
    $other = userWithRole(Role::Approver);

    $this->actingAs($other)->get(route('dashboard'))->assertRedirect(route('two-factor.show'));

    $this->actingAs($admin)
        ->put(route('admin.users.two-factor-requirement', $other), ['requirement' => 'exempt'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('rbac.users.messages.two_factor_requirement_saved'));

    expect($other->refresh()->two_factor_requirement)->toBe(User::TWO_FACTOR_EXEMPT);
    $this->actingAs($other)->get(route('dashboard'))->assertOk();
    expect(Activity::query()->where('event', AuditEvent::UserTwoFactorRequirementChanged->value)->sole()->properties['attributes'])
        ->toBe(['two_factor_requirement' => 'exempt']);

    $this->actingAs($admin)
        ->put(route('admin.users.two-factor-requirement', $other), ['requirement' => 'policy'])
        ->assertSessionHasNoErrors();

    expect($other->refresh()->two_factor_requirement)->toBeNull();
    $this->actingAs($other)->get(route('dashboard'))->assertRedirect(route('two-factor.show'));
});

it('refuses an unknown choice', function () {
    $admin = userWithRole(Role::Admin);
    $admin->forceFill(['two_factor_requirement' => User::TWO_FACTOR_EXEMPT])->save();

    $this->actingAs($admin)
        ->put(route('admin.users.two-factor-requirement', userWithRole(Role::Approver)), ['requirement' => 'sometimes'])
        ->assertSessionHasErrors('requirement');
});

it('does not let someone without users.reset-passwords choose', function () {
    $approver = userWithRole(Role::Approver);
    $approver->forceFill(['two_factor_requirement' => User::TWO_FACTOR_EXEMPT])->save();
    $other = userWithRole(Role::Approver);

    $this->actingAs($approver)
        ->put(route('admin.users.two-factor-requirement', $other), ['requirement' => 'exempt'])
        ->assertForbidden();

    expect($other->refresh()->two_factor_requirement)->toBeNull();
});

it('lets an admin set the two-factor policy, with roles, in the security settings', function () {
    $admin = userWithRole(Role::Admin);
    $admin->forceFill(['two_factor_requirement' => User::TWO_FACTOR_EXEMPT])->save();
    $approverRole = RoleModel::findByName(Role::Approver->value);

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'security'), settingsForm('security', [
            'permits.security.mfa_required_for_all' => false,
            'permits.security.mfa_required_roles' => [(string) $approverRole->id],
        ]))
        ->assertSessionHasNoErrors();

    expect(config('permits.security.mfa_required_for_all'))->toBeFalse()
        ->and(config('permits.security.mfa_required_roles'))->toBe([$approverRole->id]);

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'security'), settingsForm('security', ['permits.security.mfa_required_roles' => ['999999']]))
        ->assertSessionHasErrors('permits__security__mfa_required_roles.0');
});
