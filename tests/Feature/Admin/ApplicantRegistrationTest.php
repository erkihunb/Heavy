<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApplicantAccountCreated;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Applicants who come to the office in person: a Registrar (or an Admin)
 * opens their account and organization; the applicant sets their own
 * password from an e-mailed link.
 */

beforeEach(function () {
    Notification::fake();
    $this->registrar = staffUser(Role::Registrar);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function walkIn(array $overrides = []): array
{
    return [
        'contact_name' => 'Abebe Kebede',
        'contact_email' => ' Abebe@Example.TEST ',
        'locale' => 'am',
        'name_am' => 'አበበ የጭነት ማጓጓዣ',
        'name_en' => 'Abebe Freight',
        'tin' => '0012345678',
        'phone' => '0911 22 33 44',
        'email' => 'office@abebe.test',
        'address' => 'Bole, Addis Ababa',
        ...$overrides,
    ];
}

it('registers the applicant and their organization in one step', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.store'), walkIn())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.applicants.index'))
        ->assertSessionHas('success');

    $user = User::query()->where('email', 'abebe@example.test')->sole();
    $organization = Organization::query()->sole();

    expect($user->hasRole('applicant'))->toBeTrue()
        ->and($user->isStaff())->toBeFalse()
        ->and($user->locale)->toBe('am')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->registered_by)->toBe($this->registrar->id)
        ->and($user->organization_id)->toBe($organization->id)
        // The applicant owns the profile and may edit it, as if they had registered online.
        ->and($organization->created_by)->toBe($user->id)
        ->and($organization->phone)->toBe('+251911223344');

    Notification::assertSentTo($user, ApplicantAccountCreated::class);

    $entry = Activity::query()->where('event', AuditEvent::ApplicantRegistered->value)->sole();
    expect((int) $entry->causer_id)->toBe($this->registrar->id)
        ->and((int) $entry->subject_id)->toBe($user->id)
        ->and($entry->properties['context'])->toBe(['organization_id' => $organization->id, 'in_person' => true]);
});

it('never lets the office choose or see the password', function () {
    $this->actingAs($this->registrar)->post(route('admin.applicants.store'), walkIn(['password' => 'secret-123']));

    $user = User::query()->where('email', 'abebe@example.test')->sole();

    expect(Hash::check('secret-123', $user->password))->toBeFalse();
});

it('lets the applicant set a password from the e-mailed link and sign in', function () {
    $this->actingAs($this->registrar)->post(route('admin.applicants.store'), walkIn());
    auth()->logout();

    $user = User::query()->where('email', 'abebe@example.test')->sole();
    $token = null;
    Notification::assertSentTo($user, ApplicantAccountCreated::class, function (ApplicantAccountCreated $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'abebe@example.test',
        'password' => 'A-strong-pass-2026!',
        'password_confirmation' => 'A-strong-pass-2026!',
    ])->assertSessionHasNoErrors();

    $this->post(route('login.store'), ['email' => 'abebe@example.test', 'password' => 'A-strong-pass-2026!'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

it('validates the form', function (array $overrides, string $field) {
    Organization::factory()->create(['tin' => '0099999999']);
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($this->registrar)
        ->post(route('admin.applicants.store'), walkIn($overrides))
        ->assertSessionHasErrors($field);

    expect(User::query()->where('email', 'abebe@example.test')->exists())->toBeFalse();
})->with([
    'no name' => [['contact_name' => ''], 'contact_name'],
    'e-mail already used' => [['contact_email' => 'Taken@example.test'], 'contact_email'],
    'unknown language' => [['locale' => 'fr'], 'locale'],
    'TIN already registered' => [['tin' => '0099999999'], 'tin'],
    'bad phone' => [['phone' => '12'], 'phone'],
    'no organization name' => [['name_am' => ''], 'name_am'],
]);

it('is for registrars and admins only', function (Role $role, bool $allowed) {
    $user = staffUser($role);

    $this->actingAs($user)->get(route('admin.applicants.index'))->assertStatus($allowed ? 200 : 403);
    $this->actingAs($user)->get(route('admin.applicants.create'))->assertStatus($allowed ? 200 : 403);
    $this->actingAs($user)->post(route('admin.applicants.store'), walkIn())->assertStatus($allowed ? 302 : 403);
})->with([
    'registrar' => [Role::Registrar, true],
    'admin' => [Role::Admin, true],
    'reviewer' => [Role::Reviewer, false],
    'approver' => [Role::Approver, false],
    'cashier' => [Role::Cashier, false],
    'traffic officer' => [Role::TrafficOfficer, false],
]);

it('keeps applicants out', function () {
    $this->actingAs(applicantWithOrganization())->get(route('admin.applicants.index'))->assertForbidden();
});

it('lists applicants with where they registered, and finds them', function () {
    $this->actingAs($this->registrar)->post(route('admin.applicants.store'), walkIn());
    applicantWithOrganization(['name_am' => 'ሌላ ተቋም', 'tin' => '0055555555']);

    $this->actingAs($this->registrar)
        ->get(route('admin.applicants.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/applicants/index')
            ->has('applicants.data', 2)
            ->where('applicants.data.1.registered_by', $this->registrar->name)
            ->where('applicants.data.0.registered_by', null));

    $this->actingAs($this->registrar)
        ->get(route('admin.applicants.index', ['search' => '0012345678']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('applicants.data', 1)
            ->where('applicants.data.0.email', 'abebe@example.test'));

    $this->actingAs($this->registrar)
        ->get(route('admin.applicants.index', ['source' => 'online']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('applicants.data', 1)
            ->where('applicants.data.0.registered_by', null));
});

it('makes the registrar a staff role that needs two-factor authentication', function () {
    expect(Role::Registrar->isStaff())->toBeTrue()
        ->and(Role::staffValues())->toContain('registrar');

    $this->actingAs(userWithRole(Role::Registrar))
        ->get(route('admin.applicants.index'))
        ->assertRedirect();
});
