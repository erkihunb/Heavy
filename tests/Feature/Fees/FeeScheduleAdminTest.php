<?php

use App\Enums\PermitType;
use App\Enums\Role;
use App\Models\FeeSchedule;
use App\Models\User;
use App\Models\VehicleType;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Admins maintain the fee schedule (spec §11). Lines are added or given an end
 * date, never deleted; two lines may not price the same permit on the same day.
 */

beforeEach(function () {
    $this->admin = staffUser(Role::Admin);
    $this->cargo = VehicleType::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function feeLinePayload(array $overrides = []): array
{
    return [
        'permit_type' => PermitType::FullDay->value,
        'vehicle_type_id' => null,
        'min_days' => 1,
        'max_days' => 30,
        'amount' => '1500.50',
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        ...$overrides,
    ];
}

describe('screens', function () {
    it('lists the schedule with amounts and whether each line is in force', function () {
        FeeSchedule::factory()->forVehicleType($this->cargo)->amount(600_000)->effective('2020-01-01')->create();
        FeeSchedule::factory()->amount(100_000)->effective('2020-01-01', '2020-12-31')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.fee-schedules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/fee-schedules/index')
                ->has('schedules.data', 2)
                // Lines for every vehicle type come first.
                ->where('schedules.data.0.vehicle_type', null)
                ->where('schedules.data.0.amount.formatted', 'ETB 1,000.00')
                ->where('schedules.data.0.is_current', false)
                ->where('schedules.data.1.vehicle_type.id', $this->cargo->id)
                ->where('schedules.data.1.is_current', true));
    });

    it('filters by permit type', function () {
        FeeSchedule::factory()->forType(PermitType::Pass)->create();
        FeeSchedule::factory()->forType(PermitType::FullDay)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.fee-schedules.index', ['permit_type' => 'pass']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('schedules.data', 1)
                ->where('schedules.data.0.permit_type', 'pass')
                ->where('filters.permit_type', 'pass'));
    });

    it('offers the vehicle types on the form', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.fee-schedules.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/fee-schedules/create')
                ->has('vehicleTypes', VehicleType::query()->count()));
    });

    it('opens a line for editing', function () {
        $line = FeeSchedule::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.fee-schedules.edit', $line))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/fee-schedules/edit')
                ->where('schedule.id', $line->id));
    });
});

describe('saving', function () {
    it('adds a line, storing the amount as santim', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.fee-schedules.store'), feeLinePayload(['vehicle_type_id' => $this->cargo->id]))
            ->assertRedirect(route('admin.fee-schedules.index'))
            ->assertSessionHas('success');

        $line = FeeSchedule::query()->sole();

        expect($line->amount_cents)->toBe(150_050)
            ->and($line->permit_type)->toBe(PermitType::FullDay)
            ->and($line->vehicle_type_id)->toBe($this->cargo->id)
            ->and($line->effective_from->toDateString())->toBe('2026-01-01')
            ->and($line->effective_to)->toBeNull();
    });

    it('changes a line', function () {
        $line = FeeSchedule::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.fee-schedules.update', $line), feeLinePayload(['amount' => '6000', 'effective_to' => '2026-12-31']))
            ->assertRedirect(route('admin.fee-schedules.index'));

        expect($line->refresh()->amount_cents)->toBe(600_000)
            ->and($line->effective_to?->toDateString())->toBe('2026-12-31');
    });

    it('lets a line be changed without counting it as overlapping itself', function () {
        $line = FeeSchedule::factory()->days(1, 30)->effective('2026-01-01')->create();

        $this->actingAs($this->admin)
            ->put(route('admin.fee-schedules.update', $line), feeLinePayload())
            ->assertSessionHasNoErrors();
    });

    it('validates the line', function (array $overrides, string $field) {
        $this->actingAs($this->admin)
            ->post(route('admin.fee-schedules.store'), feeLinePayload($overrides))
            ->assertSessionHasErrors($field);

        expect(FeeSchedule::query()->count())->toBe(0);
    })->with([
        'unknown permit type' => [['permit_type' => 'night'], 'permit_type'],
        'unknown vehicle type' => [['vehicle_type_id' => 999_999], 'vehicle_type_id'],
        'zero days' => [['min_days' => 0], 'min_days'],
        'band upside down' => [['min_days' => 31, 'max_days' => 30], 'max_days'],
        'amount in words' => [['amount' => 'ሺህ'], 'amount'],
        'negative amount' => [['amount' => '-100'], 'amount'],
        'three decimals' => [['amount' => '10.005'], 'amount'],
        'end before start' => [['effective_to' => '2025-12-31'], 'effective_to'],
        'missing start' => [['effective_from' => ''], 'effective_from'],
    ]);

    it('refuses a line that prices the same permits on the same days', function (array $existing, array $new) {
        FeeSchedule::factory()->state($existing)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.fee-schedules.store'), feeLinePayload($new))
            ->assertSessionHasErrors(['min_days' => __('fees.errors.overlap')]);

        expect(FeeSchedule::query()->count())->toBe(1);
    })->with([
        'same band' => [['min_days' => 1, 'max_days' => 30], []],
        'bands share a day' => [['min_days' => 30, 'max_days' => 60], []],
        'open-ended line already in force' => [['effective_from' => '2025-01-01', 'effective_to' => null], ['effective_from' => '2027-01-01']],
        'new open-ended line reaches an old one' => [['effective_from' => '2030-01-01', 'effective_to' => '2030-12-31'], ['effective_to' => null]],
    ]);

    it('accepts lines that do not price the same permits on the same days', function (array $existing, array $new) {
        FeeSchedule::factory()->state($existing)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.fee-schedules.store'), feeLinePayload($new))
            ->assertSessionHasNoErrors();

        expect(FeeSchedule::query()->count())->toBe(2);
    })->with([
        'next band' => [['min_days' => 31, 'max_days' => 60], []],
        'other permit type' => [['permit_type' => 'pass', 'min_days' => 1, 'max_days' => 30], []],
        'old line ended before' => [['effective_from' => '2025-01-01', 'effective_to' => '2025-12-31', 'min_days' => 1, 'max_days' => 30], []],
        'replacement starts after an end date' => [['effective_from' => '2026-01-01', 'effective_to' => '2026-06-30', 'min_days' => 1, 'max_days' => 30], ['effective_from' => '2026-07-01']],
    ]);

    it('lets a line for one vehicle type sit beside a line for every type', function () {
        FeeSchedule::factory()->days(1, 30)->effective('2026-01-01')->create();

        $this->actingAs($this->admin)
            ->post(route('admin.fee-schedules.store'), feeLinePayload(['vehicle_type_id' => $this->cargo->id]))
            ->assertSessionHasNoErrors();
    });
});

describe('authorization', function () {
    it('keeps everyone but Admins out', function (Role $role) {
        $user = staffUser($role);
        $line = FeeSchedule::factory()->create();

        $this->actingAs($user)->get(route('admin.fee-schedules.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.fee-schedules.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.fee-schedules.store'), feeLinePayload())->assertForbidden();
        $this->actingAs($user)->get(route('admin.fee-schedules.edit', $line))->assertForbidden();
        $this->actingAs($user)->put(route('admin.fee-schedules.update', $line), feeLinePayload(['amount' => '1']))->assertForbidden();

        expect(FeeSchedule::query()->count())->toBe(1)
            ->and($line->refresh()->amount_cents)->toBe(100_000);
    })->with(array_values(array_filter(Role::cases(), fn (Role $role): bool => ! in_array($role, [Role::Admin, Role::Applicant], true))));

    it('sends guests to the login page', function () {
        $this->get(route('admin.fee-schedules.index'))->assertRedirect(route('login'));
    });

    it('allows Admins only on the policy', function (Role $role) {
        /** @var User $user */
        $user = userWithRole($role);
        $line = FeeSchedule::factory()->create();
        $isAdmin = $role === Role::Admin;

        expect($user->can('viewAny', FeeSchedule::class))->toBe($isAdmin)
            ->and($user->can('create', FeeSchedule::class))->toBe($isAdmin)
            ->and($user->can('update', $line))->toBe($isAdmin);
    })->with(Role::cases());
});
