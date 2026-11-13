<?php

use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The register of all permits: the review roles, Admins and Registrars see
 * every permit (Registrars drafts too), search and filter it, and open each
 * one on the page they work on it from.
 */

it('is open to the staff who work on applications, admins and registrars only', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get(route('admin.permits.index'))->assertStatus($status);
})->with([
    'reviewer' => [Role::Reviewer, 200],
    'approver' => [Role::Approver, 200],
    'admin' => [Role::Admin, 200],
    'registrar' => [Role::Registrar, 200],
    'cashier' => [Role::Cashier, 200],
    'issuer' => [Role::Issuer, 200],
    'supervisor' => [Role::Supervisor, 200],
    'traffic officer' => [Role::TrafficOfficer, 403],
]);

it('keeps applicants out', function () {
    assertSignedOut($this->actingAs(applicantWithOrganization())->get(route('admin.permits.index')));
});

it('lists every permit newest first, drafts only for registrars', function () {
    $older = Permit::factory()->submitted()->create(['created_at' => now()->subDays(3)]);
    $newer = Permit::factory()->issued()->create(['created_at' => now()->subDay()]);
    $draft = Permit::factory()->draft()->create(['created_at' => now()]);

    $this->actingAs(staffUser(Role::Registrar))
        ->get(route('admin.permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/index')
            ->has('permits.data', 3)
            ->where('permits.data.0.id', $draft->id)
            ->where('permits.data.2.id', $older->id)
            ->where('statuses', fn ($statuses) => collect($statuses)->contains('draft')));

    $this->actingAs(staffUser(Role::Reviewer))
        ->get(route('admin.permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permits.data', 2)
            ->where('permits.data.0.id', $newer->id)
            ->where('statuses', fn ($statuses) => ! collect($statuses)->contains('draft')));
});

it('finds a permit by number, plate, organization, TIN or applicant', function (string $field) {
    $organization = Organization::factory()->create(['name_am' => 'ልዩ የጭነት ድርጅት', 'tin' => '0098765432']);
    $vehicle = Vehicle::factory()->for($organization)->withPlate('ኢት-03-61234')->create();
    $permit = Permit::factory()->forVehicles($vehicle)->issued()->create(['permit_number' => 'TMA-HV-2019-004242']);
    $permit->applicant->forceFill(['name' => 'Kebede Alemu'])->save();
    Permit::factory()->issued()->create();

    $term = [
        'number' => '004242',
        'plate' => '61234',
        'organization' => 'ልዩ የጭነት',
        'tin' => '0098765432',
        'applicant' => 'Kebede',
    ][$field];

    $this->actingAs(staffUser(Role::Approver))
        ->get(route('admin.permits.index', ['search' => $term]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permits.data', 1)
            ->where('permits.data.0.id', $permit->id)
            ->where('filters.search', $term));
})->with(['number', 'plate', 'organization', 'tin', 'applicant']);

it('filters by status, type and where it was registered', function () {
    $active = Permit::factory()->active()->create(['permit_type' => PermitType::Pass]);
    $revoked = Permit::factory()->revoked()->create(['permit_type' => PermitType::FullDay]);
    $revoked->applicant->forceFill(['registered_by' => staffUser(Role::Registrar)->id])->save();

    $this->actingAs(staffUser(Role::Admin))
        ->get(route('admin.permits.index', ['status' => PermitStatus::Active->value]))
        ->assertInertia(fn (Assert $page) => $page->has('permits.data', 1)->where('permits.data.0.id', $active->id));

    $this->actingAs(staffUser(Role::Admin))
        ->get(route('admin.permits.index', ['permit_type' => 'full_day']))
        ->assertInertia(fn (Assert $page) => $page->has('permits.data', 1)->where('permits.data.0.id', $revoked->id));
});

it('opens each permit on the page the viewer works on it from', function () {
    $submitted = Permit::factory()->submitted()->create();
    $draft = Permit::factory()->draft()->create();

    $this->actingAs(staffUser(Role::Reviewer))
        ->get(route('admin.permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permits.data.0.href', route('staff.permits.show', $submitted)));

    $this->actingAs(staffUser(Role::Registrar))
        ->get(route('admin.permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permits.data.0.href', route('admin.applicants.permits.show', [$draft->applicant_id, $draft]))
            ->where('permits.data.1.href', route('admin.applicants.permits.show', [$submitted->applicant_id, $submitted])));
});

it('ignores filter values it does not know', function () {
    Permit::factory()->issued()->create();

    $this->actingAs(staffUser(Role::Admin))
        ->get(route('admin.permits.index', ['status' => 'bogus', 'permit_type' => 'x', 'from' => '12/12/2020']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('permits.data', 1)
            ->where('filters.status', null)
            ->where('filters.from', null));
});

it('filters by the payment-confirmed status (permits to issue)', function () {
    Permit::factory()->submitted()->count(2)->create();
    Permit::factory()->paymentSubmitted()->create(['status' => PermitStatus::PaymentConfirmed]);

    $this->actingAs(staffUser(Role::Issuer))
        ->get(route('admin.permits.index', ['status' => 'payment_confirmed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permits.data', 1)
            ->where('statuses', fn ($statuses) => collect($statuses)->contains('payment_confirmed')));
});

it('offers "add new permit" to those who take applications at the office', function (Role $role, bool $offered) {
    $this->actingAs(staffUser($role))
        ->get(route('admin.permits.index'))
        ->assertInertia(fn (Assert $page) => $page->where('createUrl', $offered ? route('admin.permits.create') : null));
})->with([
    'registrar' => [Role::Registrar, true],
    'admin' => [Role::Admin, true],
    'reviewer' => [Role::Reviewer, false],
]);

it('lists only the branches in the viewer\'s scope', function () {
    $bole = Branch::factory()->create();
    $yeka = Branch::factory()->create();
    $mine = Permit::factory()->submitted()->create(['branch_id' => $bole->id]);
    Permit::factory()->submitted()->create(['branch_id' => $yeka->id]);
    $reviewer = staffUser(Role::Reviewer);
    $reviewer->branchScopes()->delete();
    $reviewer->branchScopes()->create(['branch_id' => $bole->id, 'is_head_office' => false]);

    $this->actingAs($reviewer->refresh())
        ->get(route('admin.permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permits.data', 1)
            ->where('permits.data.0.id', $mine->id));
});
