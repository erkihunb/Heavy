<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Users (EUISIS RBAC): administrators open staff accounts with one or more
 * roles, change their details and roles, and deactivate or restore them.
 * Every change is audited; nobody changes their own roles or deactivates
 * themselves, and the last administrator is always kept.
 */

beforeEach(function () {
    Notification::fake();
    $this->admin = staffUser(Role::Admin);
});

it('is only for people who may see users', function (Role $role) {
    $this->actingAs(staffUser($role))->get(route('admin.users.index'))->assertForbidden();
})->with([[Role::Reviewer], [Role::Approver], [Role::Cashier], [Role::TrafficOfficer]]);

it('lists users with their roles, searchable and filtered by role and status', function () {
    $cashier = staffUser(Role::Cashier);
    $cashier->forceFill(['name' => 'Almaz Cashier'])->save();
    applicantWithOrganization();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'almaz']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.roles.0.name', 'cashier')
            ->where('users.data.0.active', true)
            ->where('users.data.0.two_factor', true));

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['role' => 'applicant']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.is_staff', false));

    $cashier->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $cashier->id));
});

it('opens a staff account with several roles and sends a link to choose a password', function () {
    $branch = Branch::factory()->create(['code' => 'BOL']);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Demo Reviewer',
            'email' => ' Demo.Reviewer@Example.test ',
            'roles' => ['reviewer', 'cashier'],
            'branch_id' => $branch->id,
        ]);

    $user = User::query()->where('email', 'demo.reviewer@example.test')->sole();
    $response->assertRedirect(route('admin.users.edit', $user))->assertSessionHas('success');

    expect($user->getRoleNames()->sort()->values()->all())->toBe(['cashier', 'reviewer'])
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->branch_id)->toBe($branch->id)
        // Their scope starts from where they work.
        ->and($user->branchScope()->branchIds)->toBe([$branch->id])
        ->and($user->branchScope()->headOffice)->toBeFalse();

    Notification::assertSentTo($user, StaffAccountCreated::class, function (StaffAccountCreated $notification) use ($user): bool {
        return str_contains((string) $notification->toMail($user)->actionUrl, '/reset-password/'.$notification->token);
    });

    $entry = Activity::query()->where('event', AuditEvent::UserCreated->value)->sole();
    expect($entry->subject_id)->toBe((string) $user->id)
        ->and((int) $entry->causer_id)->toBe($this->admin->id)
        ->and($entry->properties['attributes'])->toBe(['roles' => ['reviewer', 'cashier'], 'branch' => 'BOL'])
        ->and(json_encode($entry->properties))->not->toContain('demo.reviewer@example.test');
});

it('gives a new head-office account the head office and every branch', function () {
    Branch::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), ['name' => 'Demo Approver', 'email' => 'demo.approver@example.test', 'roles' => ['approver']])
        ->assertRedirect();

    $scope = User::query()->where('email', 'demo.approver@example.test')->sole()->branchScope();

    expect($scope->headOffice)->toBeTrue()
        ->and($scope->branchIds)->toHaveCount(2);
});

it('only opens accounts with staff roles and a new e-mail address', function (array $input, string $field) {
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), ['name' => 'X', 'email' => 'new@example.test', 'roles' => ['reviewer'], ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'no role' => [['roles' => []], 'roles'],
    'applicant role' => [['roles' => ['applicant']], 'roles.0'],
    'unknown role' => [['roles' => ['superuser']], 'roles.0'],
    'bad e-mail' => [['email' => 'not-an-email'], 'email'],
    'taken e-mail' => [['email' => 'taken@example.test'], 'email'],
]);

it('lets only an administrator give the administrator role or a global role', function () {
    $manager = staffUser(Role::Reviewer);
    $managerRole = RoleModel::query()->create(['name' => 'user_manager', 'guard_name' => 'web']);
    $managerRole->givePermissionTo(['users.view', 'users.create', 'users.assign-roles']);
    $manager->assignRole('user_manager');
    RoleModel::query()->create(['name' => 'auditor', 'guard_name' => 'web', 'scope_type' => RoleModel::SCOPE_GLOBAL]);

    foreach (['admin', 'auditor'] as $role) {
        $this->actingAs($manager)
            ->post(route('admin.users.store'), ['name' => 'X', 'email' => "{$role}@example.test", 'roles' => [$role]])
            ->assertSessionHasErrors('roles');
    }

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), ['name' => 'X', 'email' => 'auditor@example.test', 'roles' => ['auditor']])
        ->assertSessionHasNoErrors();
});

it('changes roles and records before and after', function () {
    $user = staffUser(Role::Reviewer);

    $this->actingAs($this->admin)
        ->put(route('admin.users.roles', $user), ['roles' => ['approver', 'cashier']])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->refresh()->getRoleNames()->sort()->values()->all())->toBe(['approver', 'cashier']);

    $entry = Activity::query()->where('event', AuditEvent::UserRoleChanged->value)->sole();
    expect($entry->properties['old'])->toBe(['roles' => ['reviewer']])
        ->and($entry->properties['attributes'])->toBe(['roles' => ['approver', 'cashier']]);
});

it('never lets anyone change their own roles, nor an applicant\'s', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.roles', $this->admin), ['roles' => ['reviewer']])
        ->assertForbidden();

    $applicant = applicantWithOrganization();

    $this->actingAs($this->admin)
        ->put(route('admin.users.roles', $applicant), ['roles' => ['reviewer']])
        ->assertForbidden();

    expect($this->admin->refresh()->hasRole('admin'))->toBeTrue()
        ->and($applicant->refresh()->hasRole('applicant'))->toBeTrue()
        ->and(Activity::query()->where('event', AuditEvent::UserRoleChanged->value)->count())->toBe(0);
});

it('keeps the last active administrator', function () {
    $other = staffUser(Role::Admin);
    $this->admin->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($other)
        ->put(route('admin.users.roles', $this->admin), ['roles' => ['reviewer']])
        ->assertRedirect();

    // The deactivated admin may lose the role: $other stays.
    expect($this->admin->refresh()->hasRole('admin'))->toBeFalse();

    $third = staffUser(Role::Admin);
    $this->actingAs($third)->post(route('admin.users.deactivate', $other))->assertRedirect();
    $this->actingAs($other->refresh())->get(route('dashboard'))->assertRedirect(route('login'));

    // $third is now the last active administrator: nobody can take it away.
    $fourth = staffUser(Role::Reviewer);
    RoleModel::findByName('reviewer')->givePermissionTo(['users.view', 'users.deactivate']);
    $this->actingAs($fourth->refresh())->post(route('admin.users.deactivate', $third))->assertSessionHasErrors('user');

    expect($third->refresh()->isActive())->toBeTrue();
});

it('changes a member of staff\'s name and where they work', function () {
    $user = staffUser(Role::Registrar);
    $branch = Branch::factory()->create(['code' => 'YEK']);

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), ['name' => 'Demo Registrar Two', 'branch_id' => $branch->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->refresh()->name)->toBe('Demo Registrar Two')
        ->and($user->branch_id)->toBe($branch->id)
        ->and(Activity::query()->where('event', AuditEvent::UserUpdated->value)->count())->toBe(1)
        ->and(Activity::query()->where('event', AuditEvent::UserBranchChanged->value)->count())->toBe(1);
});

it('deactivates an account: it cannot sign in, and restores it', function () {
    $user = staffUser(Role::Cashier);

    $this->actingAs($this->admin)->post(route('admin.users.deactivate', $user))->assertRedirect();

    expect($user->refresh()->isActive())->toBeFalse()
        ->and($user->deactivated_by)->toBe($this->admin->id);

    // Signed in already: signed out on the next request.
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();

    $user->forceFill(['password' => 'secret-password-1'])->save();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password-1'])
        ->assertSessionHasErrors(['email' => __('auth.deactivated')]);
    $this->assertGuest();

    $this->actingAs($this->admin)->post(route('admin.users.restore', $user))->assertRedirect();

    expect($user->refresh()->isActive())->toBeTrue()
        ->and(Activity::query()->where('event', AuditEvent::UserDeactivated->value)->count())->toBe(1)
        ->and(Activity::query()->where('event', AuditEvent::UserRestored->value)->count())->toBe(1);
});

it('never lets anyone deactivate their own account', function () {
    $this->actingAs($this->admin)->post(route('admin.users.deactivate', $this->admin))->assertForbidden();

    expect($this->admin->refresh()->isActive())->toBeTrue();
});

it('shows the edit page with what the viewer may do', function () {
    $user = staffUser(Role::Reviewer);

    $this->actingAs($this->admin)
        ->get(route('admin.users.edit', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/edit')
            ->where('user.id', $user->id)
            ->where('can.assignRoles', true)
            ->where('can.deactivate', true)
            ->has('scopes', 1)
            ->where('scopes.0.key', 'head-office'));

    $this->actingAs($this->admin)
        ->get(route('admin.users.edit', $this->admin))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.assignRoles', false)
            ->where('can.deactivate', false)
            ->where('user.sees_everywhere', true));
});
