<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Whoever holds users.reset-passwords gets a person who cannot sign in back
 * in: a temporary password they must replace, a reset link, or turning off a
 * lost two-factor device. Each user action is checked by its own permission,
 * not by whether the person may see the user list.
 */

beforeEach(function () {
    Notification::fake();
    $this->admin = staffUser(Role::Admin);
});

/** A member of staff whose only role grants exactly these permissions. */
function holderOf(Permission ...$permissions): User
{
    $role = RoleModel::query()->create(['name' => 'helper-'.uniqid(), 'guard_name' => 'web', 'scope_type' => 'global']);
    $role->givePermissionTo(array_map(fn (Permission $p) => $p->value, $permissions));
    $user = User::factory()->create(['two_factor_confirmed_at' => now(), 'two_factor_secret' => encrypt('secret')]);
    $user->assignRole($role);

    return $user->refresh();
}

it('sets a temporary password the person must replace, and ends their sessions', function () {
    $user = staffUser(Role::Cashier);

    $this->actingAs($this->admin)
        ->put(route('admin.users.password', $user), ['password' => 'Temporary-Pass-42', 'password_confirmation' => 'Temporary-Pass-42'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();
    expect(Hash::check('Temporary-Pass-42', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(Activity::query()->where('event', AuditEvent::UserPasswordReset->value)->count())->toBe(1);

    // Already on the must-change flag: it stays set (User::booted() clears it when the password changes alone).
    $this->actingAs($this->admin)
        ->put(route('admin.users.password', $user), ['password' => 'Another-Pass-42', 'password_confirmation' => 'Another-Pass-42']);
    expect($user->refresh()->must_change_password)->toBeTrue();
});

it('checks the temporary password like any other', function () {
    $user = staffUser(Role::Cashier);

    $this->actingAs($this->admin)
        ->put(route('admin.users.password', $user), ['password' => 'short', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->must_change_password)->toBeFalse();
});

it('e-mails a reset link', function () {
    $user = staffUser(Role::Cashier);

    $this->actingAs($this->admin)->post(route('admin.users.reset-link', $user))->assertSessionHas('success');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('turns off a lost two-factor device', function () {
    $user = staffUser(Role::Cashier);
    $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($this->admin)->delete(route('admin.users.two-factor', $user))->assertSessionHas('success');

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and(Activity::query()->where('event', AuditEvent::UserTwoFactorReset->value)->count())->toBe(1);
});

it('never resets your own sign-in, nor an administrator\'s without the administrator role permission', function () {
    $this->actingAs($this->admin)->post(route('admin.users.reset-link', $this->admin))->assertForbidden();

    $helper = holderOf(Permission::ViewUsers, Permission::ResetPasswords);
    $this->actingAs($helper)
        ->put(route('admin.users.password', $this->admin), ['password' => 'Temporary-Pass-42', 'password_confirmation' => 'Temporary-Pass-42'])
        ->assertForbidden();

    expect($this->admin->refresh()->must_change_password)->toBeFalse();
});

it('lets whoever holds only the reset permission reset, and nobody else', function () {
    $user = staffUser(Role::Cashier);

    $this->actingAs(holderOf(Permission::ViewUsers))->post(route('admin.users.reset-link', $user))->assertForbidden();

    $helper = holderOf(Permission::ViewUsers, Permission::ResetPasswords);
    $this->actingAs($helper)->post(route('admin.users.reset-link', $user))->assertSessionHas('success');
    $this->actingAs($helper)
        ->get(route('admin.users.edit', $user))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.resetPassword', true)
            ->where('can.deactivate', false));
});

it('lets someone who may only open accounts open one, without seeing the list', function () {
    $opener = holderOf(Permission::CreateUsers);

    $this->actingAs($opener)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($opener)->get(route('admin.users.create'))->assertOk();

    $this->actingAs($opener)
        ->post(route('admin.users.store'), ['name' => 'New Reviewer', 'email' => 'new.reviewer@example.test', 'roles' => ['reviewer']])
        ->assertRedirect(route('admin.users.create'))
        ->assertSessionHas('success');

    expect(User::query()->where('email', 'new.reviewer@example.test')->exists())->toBeTrue();
});

it('opens an account with a starting password and sends no e-mail', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Starting Cashier',
            'email' => 'starting.cashier@example.test',
            'roles' => ['cashier'],
            'password' => 'Starting-Pass-42',
            'password_confirmation' => 'Starting-Pass-42',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', __('rbac.users.messages.created_with_password'));

    $user = User::query()->where('email', 'starting.cashier@example.test')->firstOrFail();
    expect(Hash::check('Starting-Pass-42', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();
    Notification::assertNothingSentTo($user);
});
