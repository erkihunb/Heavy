<?php

use App\Domain\Permits\FeeCalculator;
use App\Enums\PermitType;
use App\Models\FeeSchedule;
use App\Models\VehicleType;
use Carbon\CarbonImmutable;

/*
 * The rates a permit can be registered at (owner's decision, 2026-11-18):
 * the office picks a fee schedule line, then the start date. A permit at a
 * line runs for the line's upper limit of days, never longer than a permit
 * may run, and a line is offered only when it is the one the calculator
 * would charge for those days.
 */

beforeEach(function () {
    config(['permits.max_duration_days' => 90]);
    $this->fees = app(FeeCalculator::class);
    $this->cargo = VehicleType::factory()->create();
    $this->crane = VehicleType::factory()->create();
    $this->on = CarbonImmutable::parse('2026-01-15', 'UTC');
});

/**
 * @return list<array{int, int}> each rate as [schedule line id, days]
 */
function ratesOffered(PermitType $type, ?int $vehicleTypeId, string $on = '2026-01-15'): array
{
    return array_map(
        fn (array $rate): array => [$rate['id'], $rate['days']],
        app(FeeCalculator::class)->rates($type, $vehicleTypeId, CarbonImmutable::parse($on, 'UTC')),
    );
}

it('offers no rate when the schedule is empty', function () {
    expect($this->fees->rates(PermitType::FullDay, $this->cargo->id, $this->on))->toBe([]);
});

it('offers the lines of the permit type, shortest first, each for its upper limit of days', function () {
    $month = FeeSchedule::factory()->days(31, 60)->amount(250_000)->create();
    $week = FeeSchedule::factory()->days(1, 7)->amount(50_000)->create();
    $fortnight = FeeSchedule::factory()->days(8, 30)->amount(100_000)->create();
    FeeSchedule::factory()->forType(PermitType::Pass)->days(1, 30)->create();

    expect($this->fees->rates(PermitType::FullDay, $this->cargo->id, $this->on))->toBe([
        ['id' => $week->id, 'min_days' => 1, 'max_days' => 7, 'days' => 7, 'amount_cents' => 50_000],
        ['id' => $fortnight->id, 'min_days' => 8, 'max_days' => 30, 'days' => 30, 'amount_cents' => 100_000],
        ['id' => $month->id, 'min_days' => 31, 'max_days' => 60, 'days' => 60, 'amount_cents' => 250_000],
    ]);
});

it('offers a line for the vehicle type before the line for every vehicle type', function () {
    $generic = FeeSchedule::factory()->days(1, 30)->amount(100_000)->create();
    $cargo = FeeSchedule::factory()->forVehicleType($this->cargo)->days(1, 30)->amount(150_000)->create();
    $longer = FeeSchedule::factory()->days(31, 60)->amount(200_000)->create();
    $shortCrane = FeeSchedule::factory()->forVehicleType($this->crane)->days(1, 20)->amount(80_000)->create();

    // For 30 days the cargo line is charged, so the line for every type is not offered to cargo.
    expect(ratesOffered(PermitType::FullDay, $this->cargo->id))->toBe([[$cargo->id, 30], [$longer->id, 60]])
        // A crane's own line covers 20 days; for 30 the line for every type is charged.
        ->and(ratesOffered(PermitType::FullDay, $this->crane->id))->toBe([[$shortCrane->id, 20], [$generic->id, 30], [$longer->id, 60]])
        ->and(ratesOffered(PermitType::FullDay, null))->toBe([[$generic->id, 30], [$longer->id, 60]]);
});

it('offers only the lines in force on the day', function () {
    $ended = FeeSchedule::factory()->days(1, 30)->effective('2025-01-01', '2026-01-14')->create();
    $current = FeeSchedule::factory()->days(1, 30)->effective('2026-01-15', '2026-01-31')->create();
    $future = FeeSchedule::factory()->days(31, 60)->effective('2026-01-16')->create();

    expect(ratesOffered(PermitType::FullDay, $this->cargo->id))->toBe([[$current->id, 30]])
        ->and(ratesOffered(PermitType::FullDay, $this->cargo->id, '2026-01-14'))->toBe([[$ended->id, 30]])
        ->and(ratesOffered(PermitType::FullDay, $this->cargo->id, '2026-02-01'))->toBe([[$future->id, 60]]);
});

it('offers only the line that would be charged when lines overlap', function () {
    FeeSchedule::factory()->days(1, 30)->effective('2025-01-01')->create();
    $newer = FeeSchedule::factory()->days(1, 30)->effective('2026-01-01')->create();

    expect(ratesOffered(PermitType::FullDay, $this->cargo->id))->toBe([[$newer->id, 30]]);
});

it('caps a rate at the longest permit allowed and leaves out lines beyond it', function () {
    $long = FeeSchedule::factory()->days(61, 180)->create();
    FeeSchedule::factory()->days(91, 365)->forType(PermitType::FullDay)->forVehicleType($this->crane)->create();
    $open = FeeSchedule::factory()->forType(PermitType::Pass)->create(); // 1–3650 days

    expect(ratesOffered(PermitType::FullDay, $this->cargo->id))->toBe([[$long->id, 90]])
        // The crane's line starts at 91 days: no permit can be that long.
        ->and(ratesOffered(PermitType::FullDay, $this->crane->id))->toBe([[$long->id, 90]])
        ->and(ratesOffered(PermitType::Pass, $this->cargo->id))->toBe([[$open->id, 90]]);

    // With a shorter limit the 61–180 line is beyond it; the open line is cut to the limit.
    config(['permits.max_duration_days' => 60]);

    expect(ratesOffered(PermitType::FullDay, $this->cargo->id))->toBe([])
        ->and(ratesOffered(PermitType::Pass, $this->cargo->id))->toBe([[$open->id, 60]]);
});

it('charges at a rate the amount of the rate', function () {
    FeeSchedule::factory()->days(1, 30)->amount(100_000)->create();
    FeeSchedule::factory()->forVehicleType($this->cargo)->days(1, 20)->amount(70_000)->create();
    FeeSchedule::factory()->days(31, 120)->amount(300_000)->create();

    foreach ([$this->cargo->id, $this->crane->id, null] as $vehicleTypeId) {
        foreach ($this->fees->rates(PermitType::FullDay, $vehicleTypeId, $this->on) as $rate) {
            expect($this->fees->amountFor(PermitType::FullDay, $vehicleTypeId, $rate['days'], $this->on))->toBe($rate['amount_cents']);
        }
    }
});

it('finds a rate by its line only among those offered', function () {
    $generic = FeeSchedule::factory()->days(1, 30)->amount(100_000)->create();
    $cargo = FeeSchedule::factory()->forVehicleType($this->cargo)->days(1, 30)->amount(150_000)->create();
    $pass = FeeSchedule::factory()->forType(PermitType::Pass)->days(1, 30)->create();

    expect($this->fees->rate(PermitType::FullDay, $this->cargo->id, $cargo->id, $this->on))
        ->toBe(['id' => $cargo->id, 'min_days' => 1, 'max_days' => 30, 'days' => 30, 'amount_cents' => 150_000])
        // Hidden by the cargo line, another vehicle type's line, another permit type's line.
        ->and($this->fees->rate(PermitType::FullDay, $this->cargo->id, $generic->id, $this->on))->toBeNull()
        ->and($this->fees->rate(PermitType::FullDay, $this->crane->id, $cargo->id, $this->on))->toBeNull()
        ->and($this->fees->rate(PermitType::FullDay, $this->cargo->id, $pass->id, $this->on))->toBeNull()
        ->and($this->fees->rate(PermitType::FullDay, $this->cargo->id, 999_999, $this->on))->toBeNull();
});
