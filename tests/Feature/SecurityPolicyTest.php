<?php

use App\Domain\Settings\ShellSettings;
use App\Enums\Role;
use App\Models\User;
use App\Security\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * System settings › Security, as in EUISIS: the password policy, sign-in
 * lockout and the maintenance banner; and the user list's row actions.
 */

function passwordFails(string $password, ?User $user = null, array $personal = []): bool
{
    return Validator::make(['password' => $password], ['password' => [new PasswordPolicy($user, $personal)]])->fails();
}

describe('the password policy', function () {
    it('refuses a common password, whatever its case', function () {
        expect(passwordFails('Password123'))->toBeTrue()
            ->and(passwordFails('Harbor-Lamp-731'))->toBeFalse();

        config(['permits.security.password_block_common' => false]);

        expect(passwordFails('password123'))->toBeFalse();
    });

    it('refuses a password containing the person\'s own details', function () {
        $user = User::factory()->create(['name' => 'Selam Tesfaye', 'email' => 'selam.t@example.test', 'username' => 'stesfaye', 'phone' => '+251911223344']);

        expect(passwordFails('Tesfaye-2019!', $user))->toBeTrue()
            ->and(passwordFails('x-STESFAYE-x', $user))->toBeTrue()
            ->and(passwordFails('pin911223344', $user))->toBeTrue()
            ->and(passwordFails('Harbor-Lamp-731', $user))->toBeFalse()
            ->and(passwordFails('Abebe-Lamp-731', null, ['Abebe Kebede']))->toBeTrue();

        config(['permits.security.password_block_personal_info' => false]);

        expect(passwordFails('Tesfaye-2019!', $user))->toBeFalse();
    });

    it('refuses a password longer than the maximum', function () {
        config(['permits.security.password_max_length' => 64]);

        expect(passwordFails(str_repeat('ab3-', 17)))->toBeTrue()
            ->and(passwordFails(str_repeat('ab3-', 16)))->toBeFalse();
    });

    it('refuses the last passwords, keeping them hashed', function () {
        $user = User::factory()->create(['password' => 'First-Harbor-11']);
        $user->forceFill(['password' => 'Second-Harbor-22'])->save();
        $user->forceFill(['password' => 'Third-Harbor-33'])->save();
        $user->refresh();

        expect(passwordFails('Third-Harbor-33', $user))->toBeTrue()
            ->and(passwordFails('First-Harbor-11', $user))->toBeTrue()
            ->and(passwordFails('Fourth-Harbor-44', $user))->toBeFalse()
            ->and(DB::table('password_histories')->where('user_id', $user->id)->count())->toBe(2)
            ->and(Hash::check('First-Harbor-11', (string) DB::table('password_histories')->where('user_id', $user->id)->orderBy('id')->value('password')))->toBeTrue();

        config(['permits.security.password_history_count' => 2]);

        expect(passwordFails('First-Harbor-11', $user))->toBeFalse()
            ->and(passwordFails('Second-Harbor-22', $user))->toBeTrue();

        config(['permits.security.password_history_count' => 0]);

        expect(passwordFails('Third-Harbor-33', $user))->toBeFalse();
    });

    it('applies when a person changes their own password', function () {
        $user = User::factory()->create(['password' => 'Old-Harbor-731']);

        $this->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'Old-Harbor-731',
                'password' => 'qwerty123',
                'password_confirmation' => 'qwerty123',
            ])
            ->assertSessionHasErrors('password');
    });

    it('is shared with every page for the checklist', function () {
        $this->actingAs(userWithRole(Role::Admin, true))
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('passwordPolicy.max', 128)
                ->where('passwordPolicy.history', 5)
                ->where('passwordPolicy.common', true)
                ->where('passwordPolicy.personal', true));
    });
});

it('locks a sign-in name after the failed attempts allowed', function () {
    config(['permits.security.max_login_attempts' => 2, 'permits.security.lockout_minutes' => 15]);
    $user = User::factory()->create(['password' => 'Right-Harbor-731']);

    foreach ([1, 2] as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-'.$attempt])
            ->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Right-Harbor-731'])
        ->assertTooManyRequests();

    $this->assertGuest();
});

it('shows the maintenance banner in the reader\'s language, or the other one', function () {
    config(['permits.maintenance_banner.enabled' => true, 'permits.maintenance_banner.message_en' => 'Down on Sunday', 'permits.maintenance_banner.message_am' => null]);

    expect(ShellSettings::shared())
        ->toMatchArray([
            'security.maintenance_banner_enabled' => true,
            'security.maintenance_banner_message_en' => 'Down on Sunday',
            'security.maintenance_banner_message_am' => 'Down on Sunday',
        ]);
});

it('saves the security settings', function () {
    $admin = userWithRole(Role::Admin, true);

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'security'), settingsForm('security', [
            'permits.security.password_history_count' => 8,
            'permits.security.max_login_attempts' => 3,
            'permits.security.lockout_minutes' => 30,
            'permits.maintenance_banner.enabled' => true,
            'permits.maintenance_banner.message_en' => 'Planned downtime',
        ]))
        ->assertSessionHasNoErrors();

    expect(config('permits.security.password_history_count'))->toBe(8)
        ->and(config('permits.security.max_login_attempts'))->toBe(3)
        ->and(config('permits.security.lockout_minutes'))->toBe(30)
        ->and(config('permits.maintenance_banner.enabled'))->toBeTrue()
        ->and(config('permits.maintenance_banner.message_en'))->toBe('Planned downtime');

    $this->actingAs($admin)
        ->get(route('admin.settings.show', 'security'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('roles')
            ->where('roles.0.users', fn (int $count) => $count >= 0));
});

describe('the user list', function () {
    it('shows the last sign-in and what may be done to each row', function () {
        $admin = userWithRole(Role::Admin, true);
        $other = userWithRole(Role::Cashier);
        $other->forceFill(['last_login_at' => now()])->save();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data', fn ($rows) => collect($rows)->contains(fn (array $row) => $row['id'] === $other->id
                    && $row['last_login'] !== null
                    && $row['can'] === ['update' => true, 'deactivate' => true, 'restore' => false]))
                ->where('users.data', fn ($rows) => collect($rows)->contains(fn (array $row) => $row['id'] === $admin->id
                    && $row['last_login'] === null
                    && $row['can']['deactivate'] === false)));
    });
});

it('lets an admin change a member of staff\'s e-mail', function () {
    $admin = userWithRole(Role::Admin, true);
    $other = userWithRole(Role::Cashier);
    $taken = userWithRole(Role::Cashier);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $other), ['name' => $other->name, 'branch_id' => null, 'email' => '  New.Address@Example.test '])
        ->assertSessionHasNoErrors();

    expect($other->refresh()->email)->toBe('new.address@example.test');

    $this->actingAs($admin)
        ->put(route('admin.users.update', $other), ['name' => $other->name, 'branch_id' => null, 'email' => $taken->email])
        ->assertSessionHasErrors('email');
});
