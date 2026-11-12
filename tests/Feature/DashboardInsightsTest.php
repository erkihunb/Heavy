<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\VerificationScan;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The charts and progress figures of the dashboard: each role gets the
 * figures that concern its work, counted in the Authority's days and months.
 */

beforeEach(function () {
    config(['localization.display_timezone' => 'Africa/Addis_Ababa']);

    // 10:00 on Hidar 3, 2019 E.C. in Addis Ababa.
    $this->travelTo(CarbonImmutable::parse('2026-11-12 07:00:00', 'UTC'));
});

function recordTransition(Permit $permit, PermitStatus $to, string $at): void
{
    test()->travelTo(CarbonImmutable::parse($at, 'UTC'), function () use ($permit, $to): void {
        $permit->transitions()->create(['from_status' => $permit->status, 'to_status' => $to]);
    });
}

function recordScan(string $result, string $at): void
{
    VerificationScan::query()->forceCreate([
        'result' => $result,
        'channel' => 'qr',
        'ip_hash' => str_repeat('a', 64),
        'scanned_at' => $at,
    ]);
}

it('gives an admin the six-month trend with the change from last month', function () {
    Permit::factory()->issued()->count(3)->create(['issued_at' => '2026-11-11 08:00:00']);
    Permit::factory()->issued()->create(['issued_at' => '2026-11-01 08:00:00']);
    Payment::factory()->verified()->create(['amount_cents' => 150_000, 'paid_at' => '2026-11-10']);
    Payment::factory()->verified()->create(['amount_cents' => 50_000, 'paid_at' => '2026-11-01']);

    $this->actingAs(staffUser(Role::Admin))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('insights.trend', 6)
            ->where('insights.trend.5.key', '2019-03')
            ->where('insights.trend.5.issued', 3)
            ->where('insights.trend.4.issued', 1)
            ->where('insights.trend.5.revenue.amount_cents', 150_000)
            ->where('month.issued', 3)
            ->where('month.previous.issued', 1)
            ->where('month.previous.revenue_cents', 50_000));
});

it('shows reviewers the applications sent in each day and this month\'s decisions', function () {
    $sent = Permit::factory()->submitted()->create();
    recordTransition($sent, PermitStatus::Submitted, '2026-11-12 06:00:00');
    // 22:00 UTC on 10 November is already 11 November in Addis Ababa.
    recordTransition($sent, PermitStatus::Submitted, '2026-11-10 22:00:00');
    // Older than fourteen days: not counted.
    recordTransition($sent, PermitStatus::Submitted, '2026-10-01 08:00:00');

    $decided = Permit::factory()->underReview()->create();
    recordTransition($decided, PermitStatus::AwaitingPayment, '2026-11-11 08:00:00');
    recordTransition($decided, PermitStatus::AwaitingPayment, '2026-11-12 06:00:00');
    recordTransition($decided, PermitStatus::ChangesRequested, '2026-11-11 09:00:00');
    recordTransition($decided, PermitStatus::Rejected, '2026-11-12 06:30:00');
    // Last month (Tikimt): not counted.
    recordTransition($decided, PermitStatus::Rejected, '2026-11-05 08:00:00');

    $this->actingAs(staffUser(Role::Reviewer))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('insights.submissions', 14)
            ->where('insights.submissions.13.date', '2026-11-12')
            ->where('insights.submissions.13.count', 1)
            ->where('insights.submissions.12.date', '2026-11-11')
            ->where('insights.submissions.12.count', 1)
            ->where('insights.decisions', ['approved' => 2, 'changes_requested' => 1, 'rejected' => 1])
            // Reviewers do not see revenue or payments.
            ->where('insights.trend', null)
            ->where('insights.payments', null)
            ->where('insights.setup', null));
});

it('shows a cashier this month\'s payments by what happened to them', function () {
    Payment::factory()->verified()->create(['amount_cents' => 150_000]);
    Payment::factory()->create(['amount_cents' => 40_000]);
    Payment::factory()->rejected()->create(['amount_cents' => 10_000]);
    Payment::factory()->verified()->create(['amount_cents' => 99_000, 'created_at' => '2026-10-01 08:00:00']);

    $this->actingAs(staffUser(Role::Cashier))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('insights.payments.verified.count', 1)
            ->where('insights.payments.verified.amount.amount_cents', 150_000)
            ->where('insights.payments.submitted.count', 1)
            ->where('insights.payments.rejected.count', 1)
            ->where('insights.setup', null));
});

it('shows a traffic officer today\'s checks by result and the last seven days', function () {
    recordScan('valid', '2026-11-12 06:00:00');
    recordScan('valid', '2026-11-12 05:00:00');
    recordScan('outside_hours', '2026-11-12 04:00:00');
    // 20:00 UTC on 11 November is 23:00 on 11 November in Addis Ababa: yesterday.
    recordScan('expired', '2026-11-11 20:00:00');

    $this->actingAs(staffUser(Role::TrafficOfficer))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('insights.scans.today.valid', 2)
            ->where('insights.scans.today.outside_hours', 1)
            ->where('insights.scans.today.expired', 0)
            ->has('insights.scans.days', 7)
            ->where('insights.scans.days.6.count', 3)
            ->where('insights.scans.days.5.count', 1)
            ->where('insights.submissions', null));
});

it('walks an applicant through getting started', function () {
    $this->actingAs(userWithRole(Role::Applicant))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('insights.setup', [
                ['key' => 'organization', 'done' => false],
                ['key' => 'vehicle', 'done' => false],
                ['key' => 'applied', 'done' => false],
                ['key' => 'issued', 'done' => false],
                ['key' => 'two_factor', 'done' => false],
            ])
            ->where('insights.submissions', null)
            ->where('insights.decisions', null));
});

it('counts only the applicant\'s own organization and shows how far a permit in force has run', function () {
    $user = applicantWithOrganization();
    $own = Permit::factory()->for($user->organization)->active()->create([
        'valid_from' => '2026-11-03',
        'valid_to' => '2026-11-22',
        'submitted_at' => '2026-11-01 08:00:00',
        'issued_at' => '2026-11-02 08:00:00',
    ]);
    recordTransition($own, PermitStatus::Submitted, '2026-11-12 06:00:00');
    recordTransition(Permit::factory()->submitted()->create(), PermitStatus::Submitted, '2026-11-12 06:00:00');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('insights.submissions.13.count', 1)
            ->where('insights.setup.0.done', true)
            ->where('insights.setup.2.done', true)
            ->where('insights.setup.3.done', true)
            // 3 to 22 November is 20 days; the 12th is the 10th of them.
            ->where('recentPermits.0.progress', ['percent' => 50, 'days_left' => 10, 'days' => 20]));
});
