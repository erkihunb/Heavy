<?php

use App\Domain\Permits\ValidityRules;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The reporting dashboard (spec §11): who may open it, and what it shows.
 */

beforeEach(function () {
    config(['localization.display_timezone' => 'Africa/Addis_Ababa']);

    // Hidar 3, 2019 E.C.
    $this->travelTo(CarbonImmutable::parse('2026-11-12 07:00:00', 'UTC'));
});

it('is open to approvers, cashiers and admins', function (Role $role) {
    $this->actingAs(staffUser($role))
        ->get(route('staff.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('staff/reports/index'));
})->with([
    'approver' => [Role::Approver],
    'cashier' => [Role::Cashier],
    'admin' => [Role::Admin],
]);

it('is closed to everyone else', function (Role $role) {
    $this->actingAs(staffUser($role))->get(route('staff.reports.index'))->assertForbidden();
})->with([
    'reviewer' => [Role::Reviewer],
    'traffic officer' => [Role::TrafficOfficer],
]);

it('asks guests to log in', function () {
    $this->get(route('staff.reports.index'))->assertRedirect(route('login'));
});

it('shows twelve Ethiopian months ending with this one, and the figures', function () {
    $today = ValidityRules::today();

    $permit = Permit::factory()->issued()->validBetween($today->subDays(2), $today->addDays(2))
        ->create(['issued_at' => '2026-11-11 08:00:00']);
    Payment::factory()->forPermit($permit)->verified()->create(['amount_cents' => 125_000, 'paid_at' => '2026-11-10']);
    Payment::factory()->verified()->create(['amount_cents' => 40_000, 'paid_at' => '2026-10-15']);

    $user = staffUser(Role::Approver);
    $user->forceFill(['locale' => 'en'])->save();

    $this->actingAs($user)
        ->get(route('staff.reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('monthly', 12)
            ->where('monthly.0.key', '2018-05')
            ->where('monthly.11.key', '2019-03')
            ->where('monthly.11.issued', 1)
            ->where('monthly.11.revenue.amount_cents', 125_000)
            ->where('monthly.10.revenue.amount_cents', 40_000)
            ->where('summary.issued_this_month', 1)
            ->where('summary.revenue_this_month.amount_cents', 125_000)
            ->where('summary.revenue_period.amount_cents', 165_000)
            ->where('summary.issued_period', 1)
            ->where('byStatus.active', 1)
            ->has('byVehicleType', 1)
            ->where('byVehicleType.0.permits', 1)
            ->where('topOrganizations.0.name', $permit->organization->name_en)
            ->where('topOrganizations.0.revenue.amount_cents', 125_000)
            ->where('expiring.days', 7)
            ->where('expiring.count', 1)
            ->where('expiring.permits.0.id', $permit->id)
            ->has('expiring.permits.0.valid_to.ethiopian'));
});
