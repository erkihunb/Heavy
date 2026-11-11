<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Users and roles (spec §11): Admins open staff accounts and change staff
 * roles; every change is audited; nobody changes their own role.
 */

beforeEach(function () {
    Notification::fake();
    $this->admin = staffUser(Role::Admin);
});

it('is only for admins', function (Role $role) {
    $this->actingAs(staffUser($role))->get(route('admin.users.index'))->assertForbidden();
})->with([[Role::Reviewer], [Role::Approver], [Role::Cashier], [Role::TrafficOfficer]]);

it('lists users with their roles, searchable and filtered by role', function () {
    $cashier = staffUser(Role::Cashier);
    $cashier->forceFill(['name' => 'Almaz Cashier'])->save();
    applicantWithOrganization();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'almaz']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.roles', ['cashier'])
            ->where('users.data.0.two_factor', true)
            ->where('users.data.0.can_change_role', true));

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['role' => 'applicant']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.can_change_role', false));
});

it('opens a staff account and sends a link to choose a password', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), ['name' => 'Kebede Reviewer', 'email' => ' Kebede@Example.test ', 'role' => 'reviewer'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $user = User::query()->where('email', 'kebede@example.test')->sole();

    expect($user->hasRole('reviewer'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();

    Notification::assertSentTo($user, StaffAccountCreated::class, function (StaffAccountCreated $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return str_contains((string) $mail->actionUrl, '/reset-password/'.$notification->token);
    });

    $entry = Activity::query()->where('event', AuditEvent::UserCreated->value)->sole();
    expect($entry->subject_id)->toBe((string) $user->id)
        ->and((int) $entry->causer_id)->toBe($this->admin->id)
        ->and($entry->properties['attributes'])->toBe(['roles' => ['reviewer']])
        ->and(json_encode($entry->properties))->not->toContain('kebede@example.test');
});

it('only opens accounts with a staff role and a new e-mail address', function (array $input, string $field) {
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), ['name' => 'X', 'email' => 'new@example.test', 'role' => 'reviewer', ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'applicant role' => [['role' => 'applicant'], 'role'],
    'unknown role' => [['role' => 'superuser'], 'role'],
    'bad e-mail' => [['email' => 'not-an-email'], 'email'],
    'taken e-mail' => [['email' => 'taken@example.test'], 'email'],
]);

it('changes a staff role and records before and after', function () {
    $user = staffUser(Role::Reviewer);

    $this->actingAs($this->admin)
        ->put(route('admin.users.role', $user), ['role' => 'approver'])
        ->assertRedirect(route('admin.users.index'));

    expect($user->refresh()->getRoleNames()->all())->toBe(['approver']);

    $entry = Activity::query()->where('event', AuditEvent::UserRoleChanged->value)->sole();
    expect($entry->properties['old'])->toBe(['roles' => ['reviewer']])
        ->and($entry->properties['attributes'])->toBe(['roles' => ['approver']]);
});

it('removes access by taking the role away', function () {
    $user = staffUser(Role::Cashier);

    $this->actingAs($this->admin)->put(route('admin.users.role', $user), ['role' => ''])->assertRedirect();

    expect($user->refresh()->roles)->toBeEmpty();
    $this->actingAs($user->refresh())->get(route('staff.payments.index'))->assertForbidden();
});

it('never lets an admin change their own role, nor an applicant\'s', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.role', $this->admin), ['role' => 'reviewer'])
        ->assertForbidden();

    $applicant = applicantWithOrganization();

    $this->actingAs($this->admin)
        ->put(route('admin.users.role', $applicant), ['role' => 'reviewer'])
        ->assertForbidden();

    expect($this->admin->refresh()->hasRole('admin'))->toBeTrue()
        ->and($applicant->refresh()->hasRole('applicant'))->toBeTrue()
        ->and(Activity::query()->where('event', AuditEvent::UserRoleChanged->value)->count())->toBe(0);
});
