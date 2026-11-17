<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Notifications\PermitNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * The full staff record: the name in three parts, a username to sign in
 * with, preferences, permissions given directly on top of roles, and what
 * the system records by itself. The employment details (institution,
 * department, job title, staff number, manager, employment status) are no
 * longer taken from the forms; what a record already holds is kept.
 */

beforeEach(function () {
    Notification::fake();
    $this->admin = staffUser(Role::Admin);
});

/** @return array<string, mixed> */
function fullRecord(array $overrides = []): array
{
    return [
        'first_name' => 'Abebe',
        'middle_name' => 'Kebede',
        'last_name' => 'Tesfaye',
        'email' => 'abebe.k@example.test',
        'username' => 'Abebe.K',
        'roles' => ['reviewer'],
        'locale' => 'en',
        'timezone' => 'Africa/Addis_Ababa',
        'theme' => 'dark',
        'notify_email' => '0',
        ...$overrides,
    ];
}

it('opens an account with the whole record', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), fullRecord())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'abebe.k@example.test')->firstOrFail();

    expect($user->name)->toBe('Abebe Kebede Tesfaye')
        ->and([$user->first_name, $user->middle_name, $user->last_name])->toBe(['Abebe', 'Kebede', 'Tesfaye'])
        ->and($user->username)->toBe('abebe.k')
        ->and($user->timezone)->toBe('Africa/Addis_Ababa')
        ->and($user->theme)->toBe('dark')
        ->and($user->wantsEmailNotifications())->toBeFalse()
        ->and($user->created_by)->toBe($this->admin->id);
});

it('still takes the whole name alone and splits it into its parts', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), ['name' => 'Sara Bekele Haile', 'email' => 'sara@example.test', 'roles' => ['cashier']])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'sara@example.test')->firstOrFail();
    expect([$user->first_name, $user->middle_name, $user->last_name])->toBe(['Sara', 'Bekele', 'Haile']);
});

it('checks the record: father\'s name, username, zone and theme', function () {
    staffUser(Role::Cashier)->forceFill(['username' => 'taken'])->save();

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), fullRecord([
            'middle_name' => '',
            'username' => 'taken',
            'timezone' => 'Mars/Olympus',
            'theme' => 'neon',
        ]))
        ->assertSessionHasErrors(['middle_name', 'username', 'timezone', 'theme']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), fullRecord(['username' => '9lives']))
        ->assertSessionHasErrors('username');
});

it('ignores employment details sent with a new account', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), fullRecord([
            'institution' => 'City Roads Authority',
            'department' => 'Permit directorate',
            'job_title' => 'Permit officer',
            'employee_number' => 'CRA-100',
            'manager_id' => 999999,
            'employment_status' => 'retired',
        ]))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'abebe.k@example.test')->firstOrFail();
    expect([$user->institution, $user->department, $user->job_title, $user->employee_number, $user->manager_id, $user->employment_status])
        ->toBe([null, null, null, null, null, null]);
});

it('keeps the employment details a record already holds when it is edited', function () {
    $manager = staffUser(Role::Approver);
    $user = staffUser(Role::Reviewer);
    $user->forceFill([
        'institution' => 'City Roads Authority',
        'department' => 'Permit directorate',
        'job_title' => 'Permit officer',
        'employee_number' => 'CRA-100',
        'manager_id' => $manager->id,
        'employment_status' => 'permanent',
    ])->save();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), ['name' => $user->name, 'branch_id' => null, 'locale' => 'en', 'job_title' => 'Changed', 'manager_id' => $user->id])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect([$user->institution, $user->department, $user->job_title, $user->employee_number, $user->manager_id, $user->employment_status])
        ->toBe(['City Roads Authority', 'Permit directorate', 'Permit officer', 'CRA-100', $manager->id, 'permanent'])
        ->and($user->locale)->toBe('en');
});

it('signs in with the username as well as the e-mail, and notes the sign-in', function () {
    $user = staffUser(Role::Cashier);
    $user->forceFill(['username' => 'cashier.one', 'password' => 'Secret-Pass-42'])->save();

    $this->post(route('login.store'), ['email' => 'Cashier.One', 'password' => 'Secret-Pass-42'])->assertSessionHasNoErrors();
    $this->post(route('login.store'), ['email' => 'nobody', 'password' => 'Secret-Pass-42'])->assertSessionHasErrors('email');

    // Two-factor follows the password; the sign-in itself is noted when it completes.
    $this->actingAs($user);
    event(new Login('web', $user, false));

    expect($user->refresh()->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull();
});

it('gives permissions directly on top of roles, audited, and only what the giver holds', function () {
    $user = staffUser(Role::Cashier);

    $this->actingAs($this->admin)
        ->put(route('admin.users.permissions', $user), ['permissions' => ['reports.view', 'permits.comment']])
        ->assertSessionHas('success');

    expect($user->refresh()->can('permits.comment'))->toBeTrue()
        ->and($user->getDirectPermissions()->pluck('name')->sort()->values()->all())->toBe(['permits.comment', 'reports.view'])
        ->and(Activity::query()->where('event', AuditEvent::UserPermissionsChanged->value)->count())->toBe(1);

    // Someone who may give roles but does not hold "audit-log.view" cannot hand it out.
    $role = RoleModel::query()->create(['name' => 'giver', 'guard_name' => 'web', 'scope_type' => 'global']);
    $role->givePermissionTo([Permission::ViewUsers->value, Permission::AssignRoles->value, Permission::CreateUsers->value]);
    $giver = User::factory()->withTwoFactor()->create();
    $giver->assignRole($role);

    $this->actingAs($giver)
        ->put(route('admin.users.permissions', $user), ['permissions' => ['reports.view', 'permits.comment', 'audit-log.view']])
        ->assertSessionHasErrors('permissions');

    $this->actingAs($giver)
        ->post(route('admin.users.store'), fullRecord(['permissions' => ['audit-log.view']]))
        ->assertSessionHasErrors('permissions.0');

    // Taking one away is always allowed; the applicant's own permission is never offered.
    $this->actingAs($giver)->put(route('admin.users.permissions', $user), ['permissions' => ['reports.view']])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->put(route('admin.users.permissions', $user), ['permissions' => ['permits.apply']])->assertSessionHasErrors('permissions.0');

    expect($user->refresh()->can('permits.comment'))->toBeFalse();
});

it('opens an account with extra permissions', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), fullRecord(['permissions' => ['reports.view']]))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'abebe.k@example.test')->firstOrFail();
    expect($user->getDirectPermissions()->pluck('name')->all())->toBe(['reports.view']);
});

it('sends no application e-mail to someone who turned them off', function () {
    $quiet = staffUser(Role::Cashier);
    $quiet->forceFill(['notification_preferences' => ['email' => false]])->save();
    $notification = new class(new Permit) extends PermitNotification
    {
        protected function key(): string
        {
            return 'test';
        }
    };

    expect($notification->via($quiet))->toBe([])
        ->and($notification->via(staffUser(Role::Reviewer)))->toBe(['mail']);
});

it('shows the record and the form choices on the edit page', function () {
    $user = staffUser(Role::Reviewer);
    $user->forceFill(['created_by' => $this->admin->id, 'last_login_ip' => '10.0.0.7', 'last_login_at' => now()])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.users.edit', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.record.created_by', $this->admin->name)
            ->where('user.record.last_login_ip', '10.0.0.7')
            ->where('user.record.two_factor', true)
            ->has('user.record.email_verified_at')
            ->has('timezones')
            ->has('permissions')
            ->missing('managers')
            ->missing('employmentStatuses'));
});

it('keeps the name parts in step with the name wherever it is written', function () {
    // Written without the parts (a seeder, applicant registration): they are its words.
    $user = User::factory()->create(['name' => 'Hanna Wolde Mariam']);
    expect([$user->first_name, $user->middle_name, $user->last_name])->toBe(['Hanna', 'Wolde', 'Mariam']);

    // The person changes their own name on their profile page.
    $user->forceFill(['name' => 'Hanna Tadesse'])->save();
    expect([$user->first_name, $user->middle_name, $user->last_name])->toBe(['Hanna', 'Tadesse', null]);

    // The parts given together with the name are kept as given.
    $user->forceFill(['name' => 'Hanna T. Bekele', 'first_name' => 'Hanna', 'middle_name' => 'T.', 'last_name' => 'Bekele'])->save();
    expect($user->refresh()->middle_name)->toBe('T.');
});

it('finds a user by username, staff number or phone', function (string $term) {
    $user = staffUser(Role::Cashier);
    $user->forceFill(['username' => 'cash.desk', 'employee_number' => 'CRA-777', 'phone' => '+251911000111'])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => $term]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $user->id));
})->with(['cash.desk', 'CRA-777', '911000111']);
