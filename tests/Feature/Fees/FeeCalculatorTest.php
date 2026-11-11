<?php

use App\Domain\Permits\FeeCalculator;
use App\Enums\PermitType;
use App\Models\FeeSchedule;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\CarbonImmutable;

/*
 * What a permit costs (spec §4.4): the schedule line for its permit type, its
 * primary vehicle's type and its number of days, in force on the day asked.
 */

beforeEach(function () {
    $this->fees = app(FeeCalculator::class);
    $this->cargo = VehicleType::factory()->create();
    $this->crane = VehicleType::factory()->create();
    $this->on = CarbonImmutable::parse('2026-01-15');
});

function feeFor(PermitType $type, ?int $vehicleTypeId, int $days, string $on): ?int
{
    return app(FeeCalculator::class)->amountFor($type, $vehicleTypeId, $days, CarbonImmutable::parse($on));
}

it('has no fee when the schedule is empty', function () {
    expect($this->fees->amountFor(PermitType::FullDay, $this->cargo->id, 10, $this->on))->toBeNull();
});

it('prices a permit from a line for every vehicle type', function () {
    FeeSchedule::factory()->amount(600_000)->create();

    expect(feeFor(PermitType::FullDay, $this->cargo->id, 10, '2026-01-15'))->toBe(600_000)
        ->and(feeFor(PermitType::FullDay, $this->crane->id, 10, '2026-01-15'))->toBe(600_000)
        ->and(feeFor(PermitType::FullDay, null, 10, '2026-01-15'))->toBe(600_000);
});

it('only uses lines for the permit type', function () {
    FeeSchedule::factory()->forType(PermitType::Pass)->amount(100_000)->create();

    expect(feeFor(PermitType::FullDay, $this->cargo->id, 10, '2026-01-15'))->toBeNull()
        ->and(feeFor(PermitType::Pass, $this->cargo->id, 10, '2026-01-15'))->toBe(100_000);
});

it('includes both ends of the duration band', function (int $days, ?int $expected) {
    FeeSchedule::factory()->days(1, 30)->amount(100_000)->create();
    FeeSchedule::factory()->days(31, 60)->amount(600_000)->create();

    expect(feeFor(PermitType::FullDay, $this->cargo->id, $days, '2026-01-15'))->toBe($expected);
})->with([
    [1, 100_000],
    [30, 100_000],
    [31, 600_000],
    [60, 600_000],
    [61, null],
]);

it('includes both ends of the period the line is in force', function (string $on, ?int $expected) {
    FeeSchedule::factory()->effective('2026-01-01', '2026-01-31')->amount(100_000)->create();

    expect(feeFor(PermitType::FullDay, $this->cargo->id, 5, $on))->toBe($expected);
})->with([
    ['2025-12-31', null],
    ['2026-01-01', 100_000],
    ['2026-01-31', 100_000],
    ['2026-02-01', null],
]);

it('treats a line without an end date as in force until replaced', function () {
    FeeSchedule::factory()->effective('2026-01-01')->amount(100_000)->create();

    expect(feeFor(PermitType::FullDay, $this->cargo->id, 5, '2099-12-31'))->toBe(100_000);
});

it('uses the line in force on the day, when the tariff changes', function () {
    FeeSchedule::factory()->effective('2025-01-01', '2025-12-31')->amount(100_000)->create();
    FeeSchedule::factory()->effective('2026-01-01')->amount(150_000)->create();

    expect(feeFor(PermitType::FullDay, $this->cargo->id, 5, '2025-12-31'))->toBe(100_000)
        ->and(feeFor(PermitType::FullDay, $this->cargo->id, 5, '2026-01-01'))->toBe(150_000);
});

it('prefers a line for the vehicle type over a line for every type', function () {
    FeeSchedule::factory()->amount(100_000)->create();
    FeeSchedule::factory()->forVehicleType($this->crane)->amount(1_500_000)->create();

    expect(feeFor(PermitType::FullDay, $this->crane->id, 10, '2026-01-15'))->toBe(1_500_000)
        ->and(feeFor(PermitType::FullDay, $this->cargo->id, 10, '2026-01-15'))->toBe(100_000);
});

it('prefers the vehicle type even over a newer line for every type', function () {
    FeeSchedule::factory()->forVehicleType($this->crane)->effective('2020-01-01')->amount(1_500_000)->create();
    FeeSchedule::factory()->effective('2026-01-01')->amount(100_000)->create();

    expect(feeFor(PermitType::FullDay, $this->crane->id, 10, '2026-01-15'))->toBe(1_500_000);
});

it('prefers the most recent start date among equally specific lines', function () {
    // The Admin form refuses this overlap; the calculator still has one answer.
    FeeSchedule::factory()->effective('2020-01-01')->amount(100_000)->create();
    FeeSchedule::factory()->effective('2025-06-01')->amount(200_000)->create();

    expect(feeFor(PermitType::FullDay, $this->cargo->id, 10, '2026-01-15'))->toBe(200_000);
});

it('sees a change to the schedule at once', function () {
    $line = FeeSchedule::factory()->amount(100_000)->create();

    expect(feeFor(PermitType::FullDay, $this->cargo->id, 10, '2026-01-15'))->toBe(100_000);

    $line->update(['amount_cents' => 250_000]);
    expect(feeFor(PermitType::FullDay, $this->cargo->id, 10, '2026-01-15'))->toBe(250_000);

    $line->delete();
    expect(feeFor(PermitType::FullDay, $this->cargo->id, 10, '2026-01-15'))->toBeNull();
});

it('prices a permit by its primary vehicle and its duration', function () {
    FeeSchedule::factory()->days(1, 10)->amount(100_000)->create();
    FeeSchedule::factory()->days(11, 3650)->amount(600_000)->create();
    FeeSchedule::factory()->forVehicleType($this->crane)->days(1, 3650)->amount(1_500_000)->create();

    $primary = Vehicle::factory()->create(['vehicle_type_id' => $this->cargo->id]);
    $trailer = Vehicle::factory()->create(['organization_id' => $primary->organization_id, 'vehicle_type_id' => $this->crane->id]);

    // Ten days, both ends counted: the trailer's type does not decide the fee.
    $short = Permit::factory()->forVehicles($primary, $trailer)->validBetween('2026-03-01', '2026-03-10')->create();
    $long = Permit::factory()->forVehicles($primary)->validBetween('2026-03-01', '2026-03-11')->create();

    expect($this->fees->amountForPermit($short, $this->on))->toBe(100_000)
        ->and($this->fees->amountForPermit($long, $this->on))->toBe(600_000);
});
