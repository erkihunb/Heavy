<?php

use App\Domain\Permits\ValidityRules;
use App\Domain\Reports\PermitReports;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\EthiopianMonth;
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
