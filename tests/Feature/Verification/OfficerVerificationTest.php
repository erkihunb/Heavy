<?php

use App\Domain\Permits\VerificationTokenService;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Enums\ScanResult;
use App\Models\Permit;
use App\Models\PermitReport;
use App\Models\Vehicle;
use App\Models\VerificationScan;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The traffic officer's view (spec §7): the same QR address shows a signed-in
 * officer the public answer plus history, recent checks and reports, logs the
 * officer, and lets them report a violation.
 */

beforeEach(function () {
    $this->officer = staffUser(Role::TrafficOfficer);

    ['token' => $this->token, 'hash' => $hash] = app(VerificationTokenService::class)->generate();
    $this->permit = Permit::factory()
        ->forVehicles(Vehicle::factory()->withPlate('አአ-03-A45301')->create())
        ->active()
        ->create(['verification_token_hash' => $hash, 'permit_number' => 'TMA-HV-2019-000555']);
});

it('shows a signed-in officer the officer view of a scanned permit', function () {
    PermitReport::query()->forceCreate(['permit_id' => $this->permit->id, 'reported_by' => $this->officer->id, 'note' => 'Earlier stop: fine.']);

    $this->actingAs($this->officer)
        ->get(route('verification.token', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/scan')
            ->where('verification.result', 'valid')
            ->where('verification.permit.permit_number', 'TMA-HV-2019-000555')
            ->where('verification.permit.id', $this->permit->id)
            ->has('verification.permit.history')
            ->has('verification.permit.scans', 1)
            ->where('verification.permit.scans.0.by', $this->officer->name)
            ->has('verification.permit.reports', 1)
            ->where('verification.permit.reports.0.note', 'Earlier stop: fine.')
            ->missing('verification.permit.fee')
            ->missing('verification.permit.reason'));
});

it('logs the officer who scanned', function () {
    $this->actingAs($this->officer)->get(route('verification.token', $this->token));

    expect(VerificationScan::query()->sole()->user_id)->toBe($this->officer->id);
});

it('shows the officer view for a revoked permit with its reason', function () {
    $this->permit->transitions()->create(['from_status' => PermitStatus::Issued, 'to_status' => PermitStatus::Revoked, 'note' => 'Forged receipt.']);
    $this->permit->forceFill(['status' => PermitStatus::Revoked])->save();

    $this->actingAs($this->officer)
        ->get(route('verification.token', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('verification.result', ScanResult::Revoked->value)
            ->where('verification.permit.history.0.note', 'Forged receipt.'));
});

it('shows only the result when nothing is found', function () {
    $this->actingAs($this->officer)
        ->get(route('verification.token', str_repeat('Z', 43)))
        ->assertInertia(fn (Assert $page) => $page
            ->component('officer/scan')
            ->where('verification.result', 'not_found')
            ->where('verification.permit', null));
});

it('looks a permit up by number and plate', function () {
    $this->actingAs($this->officer)->get(route('officer.verify'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('officer/lookup')
        // The in-page scanner accepts only QR codes pointing at this address.
        ->where('verifyBase', rtrim((string) config('permits.verify_url'), '/')));

    $this->actingAs($this->officer)
        ->post(route('verification.lookup'), ['permit_number' => 'TMA-HV-2019-000555', 'plate' => 'አአ-03-A45301'])
        ->assertInertia(fn (Assert $page) => $page->component('officer/scan')->where('verification.result', 'valid'));
});

it('records a violation report', function () {
    $this->actingAs($this->officer)
        ->from(route('verification.token', $this->token))
        ->post(route('officer.permits.reports.store', $this->permit), ['note' => 'Moving at 08:00 on Churchill Road.'])
        ->assertRedirect(route('verification.token', $this->token))
        ->assertSessionHas('success', __('verification.officer.report.saved'));

    $report = PermitReport::query()->sole();

    expect($report->permit_id)->toBe($this->permit->id)
        ->and($report->reported_by)->toBe($this->officer->id)
        ->and($report->note)->toBe('Moving at 08:00 on Churchill Road.');
});

it('requires a report to say something', function () {
    $this->actingAs($this->officer)
        ->post(route('officer.permits.reports.store', $this->permit), ['note' => ''])
        ->assertSessionHasErrors('note');

    expect(PermitReport::query()->count())->toBe(0);
});

it('cannot report on a permit that was never issued', function () {
    $draft = Permit::factory()->underReview()->create();

    $this->actingAs($this->officer)
        ->post(route('officer.permits.reports.store', $draft), ['note' => 'x'])
        ->assertNotFound();
});

it('keeps the officer tools to officers and Admins', function (Role $role) {
    $user = $role === Role::Applicant ? applicantWithOrganization() : staffUser($role);

    $this->actingAs($user)->get(route('officer.verify'))->assertForbidden();
    $this->actingAs($user)->post(route('officer.permits.reports.store', $this->permit), ['note' => 'x'])->assertForbidden();

    // They see the public page when they open a QR address.
    $this->actingAs($user)
        ->get(route('verification.token', $this->token))
        ->assertOk()
        ->assertSee('data-result="valid"', false);
})->with([Role::Applicant, Role::Reviewer, Role::Approver, Role::Cashier]);

it('gives Admins the officer view as well', function () {
    $this->actingAs(staffUser(Role::Admin))
        ->get(route('verification.token', $this->token))
        ->assertInertia(fn (Assert $page) => $page->component('officer/scan'));
});
