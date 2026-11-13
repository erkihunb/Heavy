<?php

use App\Domain\Permits\ValidityRules;
use App\Domain\Reports\PermitReports;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VerificationScan;
use App\Support\EthiopianMonth;
use App\Support\Rbac\BranchScope;
use Carbon\CarbonImmutable;

/*
 * The figures of the reporting dashboard (spec §11), counted in Ethiopian
 * months in the Authority's timezone.
 */

beforeEach(function () {
    config(['localization.display_timezone' => 'Africa/Addis_Ababa']);

    // Hidar 3, 2019 E.C. Hidar runs from 10 November to 9 December 2026.
    $this->travelTo(CarbonImmutable::parse('2026-11-12 07:00:00', 'UTC'));

    $this->reports = app(PermitReports::class);
    $this->hidar = new EthiopianMonth(2019, 3);
    $this->tikimt = new EthiopianMonth(2019, 2);
});

it('counts permits issued in a month by the time in Addis Ababa', function () {
    // 21:30 UTC on 9 November is 00:30 on 10 November (Hidar 1) in Addis Ababa.
    Permit::factory()->issued()->create(['issued_at' => '2026-11-09 21:30:00']);
    // 20:30 UTC on 9 November is still 23:30 on the last day of Tikimt.
    Permit::factory()->issued()->create(['issued_at' => '2026-11-09 20:30:00']);
    // Revoked since, but it was issued in Hidar.
    Permit::factory()->revoked()->create(['issued_at' => '2026-11-11 08:00:00']);
    Permit::factory()->awaitingPayment()->create();

    expect($this->reports->issuedIn($this->hidar))->toBe(2)
        ->and($this->reports->issuedIn($this->tikimt))->toBe(1);
});

it('sums verified payments by the day they were paid', function () {
    Payment::factory()->verified()->create(['amount_cents' => 150_000, 'paid_at' => '2026-11-10']);
    Payment::factory()->verified()->create(['amount_cents' => 50_000, 'paid_at' => '2026-12-09']);
    Payment::factory()->verified()->create(['amount_cents' => 70_000, 'paid_at' => '2026-11-09']);
    Payment::factory()->rejected()->create(['amount_cents' => 999_999, 'paid_at' => '2026-11-11']);
    Payment::factory()->create(['amount_cents' => 888_888, 'paid_at' => '2026-11-11']);

    expect($this->reports->revenueIn($this->hidar))->toBe(200_000)
        ->and($this->reports->revenueIn($this->tikimt))->toBe(70_000);

    expect($this->reports->monthly([$this->tikimt, $this->hidar]))->toBe([
        ['month' => $this->tikimt, 'issued' => 0, 'revenue_cents' => 70_000],
        ['month' => $this->hidar, 'issued' => 0, 'revenue_cents' => 200_000],
    ]);
});

it('counts permits by the status people see today', function () {
    $today = ValidityRules::today();

    Permit::factory()->submitted()->count(2)->create();
    Permit::factory()->issued()->create();
    Permit::factory()->active()->create();
    // Its last day has passed but the nightly job has not marked it yet.
    Permit::factory()->issued()->validBetween($today->subDays(20), $today->subDay())->create();
    Permit::factory()->expired()->create();
    Permit::factory()->revoked()->create();

    $counts = $this->reports->byStatus();

    expect($counts)->toHaveKeys(['draft', 'submitted', 'issued', 'active', 'expired', 'revoked', 'withdrawn'])
        ->and($counts['submitted'])->toBe(2)
        ->and($counts['issued'])->toBe(1)
        ->and($counts['active'])->toBe(1)
        ->and($counts['expired'])->toBe(2)
        ->and($counts['revoked'])->toBe(1)
        ->and($counts['draft'])->toBe(0);
});

it('groups issued permits by the type of their primary vehicle', function () {
    $crane = VehicleType::factory()->create(['name_am' => 'ክሬን', 'name_en' => 'Crane']);
    $tanker = VehicleType::factory()->create(['name_am' => 'ታንከር', 'name_en' => 'Tanker']);

    $issue = function (VehicleType $type, string $issuedAt): void {
        $vehicle = Vehicle::factory()->create(['vehicle_type_id' => $type->id]);
        $trailer = Vehicle::factory()->create(['organization_id' => $vehicle->organization_id]);

        Permit::factory()->forVehicles($vehicle, $trailer)->issued()->create(['issued_at' => $issuedAt]);
    };

    $issue($tanker, '2026-11-11 08:00:00');
    $issue($tanker, '2026-10-20 08:00:00');
    $issue($crane, '2026-11-11 09:00:00');
    // Before the period: not counted.
    $issue($crane, '2026-09-01 08:00:00');

    $rows = $this->reports->byVehicleType($this->tikimt, $this->hidar);

    expect(array_map(fn (array $row): array => [$row['name_en'], $row['permits']], $rows))
        ->toBe([['Tanker', 2], ['Crane', 1]]);
});

it('ranks organizations by permits issued, with their verified revenue', function () {
    $busy = Organization::factory()->create(['name_en' => 'Busy Haulage']);
    $quiet = Organization::factory()->create(['name_en' => 'Quiet Cranes']);

    $issue = function (Organization $organization, int $paid = 0): Permit {
        $vehicle = Vehicle::factory()->create(['organization_id' => $organization->id]);
        $permit = Permit::factory()->forVehicles($vehicle)->issued()->create(['issued_at' => '2026-11-11 08:00:00']);

        if ($paid > 0) {
            Payment::factory()->forPermit($permit)->verified()->create(['amount_cents' => $paid]);
        }

        return $permit;
    };

    $issue($busy, 100_000);
    $issue($busy, 120_000);
    $rejectedToo = $issue($busy);
    Payment::factory()->forPermit($rejectedToo)->rejected()->create(['amount_cents' => 500_000]);
    $issue($quiet, 80_000);

    $rows = $this->reports->topOrganizations($this->hidar, $this->hidar);

    expect(array_map(fn (array $row): array => [$row['name_en'], $row['permits'], $row['revenue_cents']], $rows))
        ->toBe([['Busy Haulage', 3, 220_000], ['Quiet Cranes', 1, 80_000]]);

    expect($this->reports->topOrganizations($this->hidar, $this->hidar, limit: 1))->toHaveCount(1);
});

it('lists issued permits whose last day is within the next days, soonest first', function () {
    $today = ValidityRules::today();

    $later = Permit::factory()->issued()->validBetween($today->subDays(5), $today->addDays(3))->create();
    $soonest = Permit::factory()->issued()->validBetween($today->subDays(5), $today)->create();
    Permit::factory()->issued()->validBetween($today->subDays(5), $today->addDays(8))->create();
    Permit::factory()->revoked()->validBetween($today->subDays(5), $today->addDays(2))->create();
    Permit::factory()->issued()->validBetween($today->subDays(5), $today->subDay())->create();

    $expiring = $this->reports->expiringSoon(7);

    expect($expiring['count'])->toBe(2)
        ->and(array_column($expiring['permits'], 'id'))->toBe([$soonest->id, $later->id])
        ->and($expiring['permits'][0]['valid_to'])->toBe($today->toDateString())
        ->and($expiring['permits'][0]['plate'])->toBe($soonest->vehicles->first()->plate_normalized);

    expect($this->reports->expiringSoon(7, limit: 1))
        ->count->toBe(2)
        ->permits->toHaveCount(1);
});

it('counts issued permits and their revenue by branch, the head office apart', function () {
    $branch = Branch::factory()->create();

    $atBranch = Permit::factory()->issued()->count(2)->create(['issued_at' => '2026-11-11 08:00:00', 'branch_id' => $branch->id]);
    Payment::factory()->forPermit($atBranch[0])->verified()->create(['amount_cents' => 90_000]);
    Permit::factory()->issued()->create(['issued_at' => '2026-11-11 08:00:00', 'branch_id' => null]);

    expect($this->reports->byBranch($this->hidar, $this->hidar))->toBe([
        ['branch_id' => $branch->id, 'name_am' => $branch->name_am, 'name_en' => $branch->name_en, 'permits' => 2, 'revenue_cents' => 90_000],
        ['branch_id' => null, 'name_am' => null, 'name_en' => null, 'permits' => 1, 'revenue_cents' => 0],
    ]);

    expect($this->reports->byBranch($this->hidar, $this->hidar, BranchScope::of(true, [])))->toHaveCount(1);
});

it('counts the checks of the period by result and by who made them', function () {
    $officer = User::factory()->create();
    $scan = fn (string $result, string $at, ?int $userId = null) => VerificationScan::query()->forceCreate([
        'result' => $result, 'channel' => 'qr', 'ip_hash' => str_repeat('a', 64), 'scanned_at' => $at, 'user_id' => $userId,
    ]);

    $scan('valid', '2026-11-11 08:00:00', $officer->id);
    $scan('valid', '2026-11-11 09:00:00');
    $scan('expired', '2026-11-11 10:00:00');
    $scan('valid', '2026-10-01 10:00:00');

    $scans = $this->reports->scans($this->hidar, $this->hidar);

    expect($scans['total'])->toBe(3)
        ->and($scans['by_officers'])->toBe(1)
        ->and($scans['by_public'])->toBe(2)
        ->and($scans['results']['valid'])->toBe(2)
        ->and($scans['results']['expired'])->toBe(1)
        ->and($scans['results']['not_found'])->toBe(0);
});

it('measures the time from filing to issue, and the applications on their way', function () {
    Permit::factory()->issued()->create(['submitted_at' => '2026-11-09 08:00:00', 'issued_at' => '2026-11-11 08:00:00']);
    Permit::factory()->issued()->create(['submitted_at' => '2026-11-10 08:00:00', 'issued_at' => '2026-11-11 08:00:00']);
    Permit::factory()->submitted()->create(['submitted_at' => '2026-11-07 07:00:00']);
    Permit::factory()->awaitingPayment()->create(['submitted_at' => '2026-11-11 07:00:00']);
    Permit::factory()->rejected()->create();

    expect($this->reports->processing($this->hidar, $this->hidar))->toBe([
        'measured' => 2,
        'average_days' => 1.5,
        'in_progress' => 2,
        'oldest_days' => 5,
    ]);
});

it('says nothing about processing time when no permit was issued', function () {
    expect($this->reports->processing($this->hidar, $this->hidar))->toBe([
        'measured' => 0, 'average_days' => null, 'in_progress' => 0, 'oldest_days' => null,
    ]);
});

it('counts the receipts of the period by status, and the verified ones by method', function () {
    Payment::factory()->verified()->create(['amount_cents' => 150_000, 'paid_at' => '2026-11-10', 'method' => PaymentMethod::Cash]);
    Payment::factory()->verified()->create(['amount_cents' => 50_000, 'paid_at' => '2026-11-11', 'method' => PaymentMethod::Cash]);
    Payment::factory()->verified()->create(['amount_cents' => 70_000, 'paid_at' => '2026-11-11', 'method' => PaymentMethod::MobileMoney]);
    Payment::factory()->rejected()->create(['amount_cents' => 10_000, 'paid_at' => '2026-11-11']);
    Payment::factory()->create(['amount_cents' => 20_000, 'paid_at' => '2026-11-11']);
    Payment::factory()->verified()->create(['amount_cents' => 999_999, 'paid_at' => '2026-11-09']);

    expect($this->reports->payments($this->hidar, $this->hidar))->toBe([
        'statuses' => [
            'submitted' => ['count' => 1, 'amount_cents' => 20_000],
            'verified' => ['count' => 3, 'amount_cents' => 270_000],
            'rejected' => ['count' => 1, 'amount_cents' => 10_000],
        ],
        'methods' => [
            ['method' => 'cash', 'count' => 2, 'amount_cents' => 200_000],
            ['method' => 'mobile_money', 'count' => 1, 'amount_cents' => 70_000],
        ],
    ]);
});
