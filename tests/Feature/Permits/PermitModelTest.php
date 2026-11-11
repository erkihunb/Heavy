<?php

use App\Domain\Permits\PermitTransitions;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\VehicleRole;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Runs the statement in a savepoint and asserts the database refuses it.
 */
function expectDatabaseToRefuse(Closure $statement, ?string $sqlState = null): void
{
    try {
        DB::transaction($statement);
    } catch (QueryException $exception) {
        expect($exception)->toBeInstanceOf(QueryException::class);

        if ($sqlState !== null) {
            expect($exception->getCode())->toBe($sqlState);
        }

        return;
    }

    test()->fail('Expected the database to refuse the statement.');
}

describe('effective status', function () {
    beforeEach(function () {
        // Valid from 1 to 10 December 2026.
        $this->issued = Permit::factory()->issued()->validBetween('2026-12-01', '2026-12-10')->create();
        $this->at = fn (string $moment): CarbonImmutable => CarbonImmutable::parse($moment, 'Africa/Addis_Ababa');
    });

    it('is issued before the first day, active on the days covered and expired after them', function (string $moment, PermitStatus $expected) {
        expect($this->issued->effectiveStatus(($this->at)($moment)))->toBe($expected)
            // What is stored never changes.
            ->and($this->issued->status)->toBe(PermitStatus::Issued);
    })->with([
        'long before' => ['2026-11-11 12:00', PermitStatus::Issued],
        'the evening before the first day' => ['2026-11-30 23:59', PermitStatus::Issued],
        'the first minute of the first day' => ['2026-12-01 00:00', PermitStatus::Active],
        'in the middle' => ['2026-12-05 12:00', PermitStatus::Active],
        'the last minute of the last day' => ['2026-12-10 23:59', PermitStatus::Active],
        'the day after the last day' => ['2026-12-11 00:00', PermitStatus::Expired],
        'long after' => ['2027-06-01 12:00', PermitStatus::Expired],
    ]);

    it('counts days in the display timezone, not in UTC', function () {
        // 00:30 on 1 December in Addis Ababa is still 30 November in UTC.
        expect($this->issued->effectiveStatus(CarbonImmutable::parse('2026-11-30 21:30', 'UTC')))->toBe(PermitStatus::Active)
            // 00:30 on 11 December in Addis Ababa is still 10 December in UTC.
            ->and($this->issued->effectiveStatus(CarbonImmutable::parse('2026-12-10 21:30', 'UTC')))->toBe(PermitStatus::Expired)
            ->and($this->issued->effectiveStatus(CarbonImmutable::parse('2026-12-10 20:30', 'UTC')))->toBe(PermitStatus::Active);
    });

    it('is active on the only day of a one-day permit', function () {
        $permit = Permit::factory()->issued()->validBetween('2026-12-05', '2026-12-05')->create();

        expect($permit->effectiveStatus(($this->at)('2026-12-04 12:00')))->toBe(PermitStatus::Issued)
            ->and($permit->effectiveStatus(($this->at)('2026-12-05 12:00')))->toBe(PermitStatus::Active)
            ->and($permit->effectiveStatus(($this->at)('2026-12-06 12:00')))->toBe(PermitStatus::Expired);
    });

    it('stays revoked whatever the date', function (string $moment) {
        $permit = Permit::factory()->revoked()->validBetween('2026-12-01', '2026-12-10')->create();

        expect($permit->effectiveStatus(($this->at)($moment)))->toBe(PermitStatus::Revoked);
    })->with(['2026-11-20 12:00', '2026-12-05 12:00', '2026-12-20 12:00']);

    it('is the stored status in every stage other than issued', function (PermitStatus $status) {
        $permit = Permit::factory()->validBetween('2026-12-01', '2026-12-10')->create(['status' => $status]);

        expect($permit->effectiveStatus(($this->at)('2026-12-05 12:00')))->toBe($status)
            ->and($permit->effectiveStatus(($this->at)('2026-12-20 12:00')))->toBe($status);
    })->with(array_values(array_filter(
        PermitStatus::stored(),
        fn (PermitStatus $status): bool => $status !== PermitStatus::Issued,
    )));

    it('finds permits by their effective status in a query, exactly as the model reports it', function () {
        $today = ValidityRules::today();

        $permits = [
            'upcoming' => Permit::factory()->issued()->validBetween($today->addDay(), $today->addDays(5))->create(),
            'starts today' => Permit::factory()->issued()->validBetween($today, $today->addDays(5))->create(),
            'ends today' => Permit::factory()->issued()->validBetween($today->subDays(5), $today)->create(),
            'ended yesterday' => Permit::factory()->issued()->validBetween($today->subDays(5), $today->subDay())->create(),
            'marked expired' => Permit::factory()->expired()->create(),
            'revoked' => Permit::factory()->revoked()->validBetween($today->subDays(5), $today->addDays(5))->create(),
            'awaiting payment' => Permit::factory()->awaitingPayment()->validBetween($today->subDays(5), $today->addDays(5))->create(),
        ];

        $ids = array_map(fn (Permit $permit): string => $permit->id, $permits);

        $labels = fn (PermitStatus $status): array => Permit::query()
            ->whereKey(array_values($ids))
            ->inEffectiveStatus($status)
            ->pluck('id')
            ->map(fn (string $id): string => (string) array_search($id, $ids, true))
            ->sort()
            ->values()
            ->all();

        expect($labels(PermitStatus::Issued))->toBe(['upcoming'])
            ->and($labels(PermitStatus::Active))->toBe(['ends today', 'starts today'])
            ->and($labels(PermitStatus::Expired))->toBe(['ended yesterday', 'marked expired'])
            ->and($labels(PermitStatus::Revoked))->toBe(['revoked'])
            ->and($labels(PermitStatus::AwaitingPayment))->toBe(['awaiting payment'])
            ->and($labels(PermitStatus::Draft))->toBe([]);

        $now = CarbonImmutable::now();

        foreach ($permits as $permit) {
            expect(Permit::query()->inEffectiveStatus($permit->effectiveStatus($now))->whereKey($permit->id)->exists())->toBeTrue();
        }
    });
});

it('counts the first and the last day in the duration', function () {
    expect(Permit::factory()->validBetween('2026-12-01', '2026-12-01')->create()->durationDays())->toBe(1)
        ->and(Permit::factory()->validBetween('2026-12-01', '2026-12-10')->create()->durationDays())->toBe(10)
        ->and(Permit::factory()->validBetween('2026-12-25', '2027-01-05')->create()->durationDays())->toBe(12);
});

it('lists the primary vehicle before the trailers', function () {
    $primary = Vehicle::factory()->withPlate('ኢት-03-90003')->create();
    $trailers = [
        Vehicle::factory()->for($primary->organization)->withPlate('ኢት-03-90002')->create(),
        Vehicle::factory()->for($primary->organization)->withPlate('ኢት-03-90001')->create(),
    ];

    $permit = Permit::factory()->forVehicles($primary, ...$trailers)->create();

    expect($permit->vehicles->pluck('plate_normalized')->all())->toBe(['ኢት-03-90003', 'ኢት-03-90001', 'ኢት-03-90002'])
        ->and($permit->vehicles[0]->pivot->role)->toBe(VehicleRole::Primary)
        ->and($permit->vehicles[1]->pivot->role)->toBe(VehicleRole::Trailer)
        ->and($permit->vehicles[0]->pivot->is_blocking)->toBeFalse()
        ->and($primary->permits()->sole()->id)->toBe($permit->id);
});

it('never mass-assigns what only an Action may decide', function () {
    $permit = (new Permit)->forceFill(['status' => PermitStatus::Draft]);

    $permit->fill([
        'reason' => 'ምክንያት',
        'status' => 'issued',
        'permit_number' => 'TMA-HV-2019-000001',
        'organization_id' => 1,
        'applicant_id' => 1,
        'approved_by' => 1,
        'fee_cents' => 0,
    ]);

    expect($permit->reason)->toBe('ምክንያት')
        ->and($permit->status)->toBe(PermitStatus::Draft)
        ->and($permit->permit_number)->toBeNull()
        ->and($permit->organization_id)->toBeNull()
        ->and($permit->applicant_id)->toBeNull()
        ->and($permit->approved_by)->toBeNull()
        ->and($permit->fee_cents)->toBeNull();
});

describe('append-only history', function () {
    beforeEach(function () {
        $this->permit = Permit::factory()->submitted()->create();
        $this->actor = User::factory()->create();

        app(PermitTransitions::class)->record($this->permit, PermitStatus::Draft, PermitStatus::Submitted, $this->actor, 'ማስታወሻ');

        $this->row = PermitStatusTransition::sole();
    });

    it('records who moved the permit from which status to which, when and why', function () {
        expect($this->row->permit_id)->toBe($this->permit->id)
            ->and($this->row->from_status)->toBe(PermitStatus::Draft)
            ->and($this->row->to_status)->toBe(PermitStatus::Submitted)
            ->and($this->row->actor->is($this->actor))->toBeTrue()
            ->and($this->row->note)->toBe('ማስታወሻ')
            ->and($this->row->created_at)->not->toBeNull()
            ->and($this->row->permit->is($this->permit))->toBeTrue();
    });

    it('accepts a row written by the system, without an actor', function () {
        app(PermitTransitions::class)->record($this->permit, PermitStatus::Issued, PermitStatus::Expired, null);

        $latest = $this->permit->transitions()->get()->last();

        expect($latest->actor_id)->toBeNull()
            ->and($latest->note)->toBeNull()
            ->and($this->permit->transitions()->count())->toBe(2);
    });

    it('refuses to change a row through the model', function () {
        $this->row->note = 'ተቀይሯል';

        expect(fn () => $this->row->save())->toThrow(LogicException::class)
            ->and(fn () => $this->row->update(['to_status' => PermitStatus::Issued]))->toThrow(LogicException::class);

        expect(PermitStatusTransition::sole()->note)->toBe('ማስታወሻ');
    });

    it('refuses to delete a row through the model', function () {
        expect(fn () => $this->row->delete())->toThrow(LogicException::class);

        expect(PermitStatusTransition::count())->toBe(1);
    });

    it('refuses an UPDATE in the database itself', function () {
        expectDatabaseToRefuse(fn () => DB::table('permit_status_transitions')
            ->where('id', $this->row->id)
            ->update(['note' => 'ተቀይሯል']));

        expectDatabaseToRefuse(fn () => PermitStatusTransition::query()->update(['to_status' => 'issued']));

        expectDatabaseToRefuse(fn () => DB::statement('UPDATE permit_status_transitions SET actor_id = NULL'));

        $row = PermitStatusTransition::sole();

        expect($row->note)->toBe('ማስታወሻ')
            ->and($row->to_status)->toBe(PermitStatus::Submitted)
            ->and($row->actor_id)->toBe($this->actor->id);
    });

    it('refuses a DELETE in the database itself', function () {
        expectDatabaseToRefuse(fn () => DB::table('permit_status_transitions')->where('id', $this->row->id)->delete());

        expectDatabaseToRefuse(fn () => PermitStatusTransition::query()->delete());

        expectDatabaseToRefuse(fn () => DB::statement('DELETE FROM permit_status_transitions'));

        expect(PermitStatusTransition::count())->toBe(1);
    });

    it('keeps a permit that has history from being deleted', function () {
        expectDatabaseToRefuse(fn () => DB::table('permits')->where('id', $this->permit->id)->delete());

        expect(Permit::whereKey($this->permit->id)->exists())->toBeTrue()
            ->and(PermitStatusTransition::count())->toBe(1);
    });

    it('keeps a user who appears in the history from being deleted', function () {
        expectDatabaseToRefuse(fn () => DB::table('users')->where('id', $this->actor->id)->delete());

        expect(User::whereKey($this->actor->id)->exists())->toBeTrue();
    });
});

describe('schema constraints', function () {
    it('refuses a permit whose last day is before its first', function () {
        expectDatabaseToRefuse(
            fn () => Permit::factory()->withoutVehicles()->validBetween('2026-12-10', '2026-12-09')->create(),
            '23000',
        );

        $permit = Permit::factory()->withoutVehicles()->validBetween('2026-12-10', '2026-12-20')->create();

        expectDatabaseToRefuse(
            fn () => DB::table('permits')->where('id', $permit->id)->update(['valid_to' => '2026-12-01']),
            '23000',
        );

        expect(Permit::sole()->valid_to->toDateString())->toBe('2026-12-20');
    });

    it('accepts a permit for a single day', function () {
        $permit = Permit::factory()->validBetween('2026-12-10', '2026-12-10')->create();

        expect($permit->refresh()->valid_from->equalTo($permit->valid_to))->toBeTrue();
    });

    it('allows only one primary vehicle per permit', function () {
        $permit = Permit::factory()->create();
        $second = Vehicle::factory()->create(['organization_id' => $permit->organization_id]);

        $row = fn (Vehicle $vehicle, string $role): array => [
            'permit_id' => $permit->id,
            'vehicle_id' => $vehicle->id,
            'role' => $role,
            'period_from' => '2026-12-01', 'period_to' => '2026-12-10',
            'is_blocking' => false,
        ];

        expectDatabaseToRefuse(fn () => DB::table('permit_vehicles')->insert($row($second, 'primary')), '23000');

        // Any number of trailers.
        DB::table('permit_vehicles')->insert([
            $row($second, 'trailer'),
            $row(Vehicle::factory()->create(['organization_id' => $permit->organization_id]), 'trailer'),
        ]);

        expectDatabaseToRefuse(fn () => DB::table('permit_vehicles')
            ->where('permit_id', $permit->id)
            ->where('vehicle_id', $second->id)
            ->update(['role' => 'primary']), '23000');

        expect(permitVehicleRows($permit)->pluck('role')->all())->toBe(['primary', 'trailer', 'trailer']);
    });

    it('lists a vehicle only once on a permit', function () {
        $vehicle = Vehicle::factory()->create();
        $permit = Permit::factory()->forVehicles($vehicle)->create();

        expectDatabaseToRefuse(fn () => DB::table('permit_vehicles')->insert([
            'permit_id' => $permit->id,
            'vehicle_id' => $vehicle->id,
            'role' => 'trailer',
            'period_from' => '2026-12-01', 'period_to' => '2026-12-10',
            'is_blocking' => false,
        ]), '23000');
    });

    it('needs a period on every vehicle row', function () {
        $permit = Permit::factory()->withoutVehicles()->create();

        expectDatabaseToRefuse(fn () => DB::table('permit_vehicles')->insert([
            'permit_id' => $permit->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'role' => 'primary',
            'is_blocking' => false,
        ]));
    });

    it('never stores the same permit number twice', function () {
        Permit::factory()->awaitingPayment()->create(['permit_number' => 'TMA-HV-2019-000001']);

        expectDatabaseToRefuse(
            fn () => Permit::factory()->awaitingPayment()->create(['permit_number' => 'TMA-HV-2019-000001']),
            '23000',
        );

        $other = Permit::factory()->awaitingPayment()->create(['permit_number' => 'TMA-HV-2019-000002']);

        expectDatabaseToRefuse(
            fn () => DB::table('permits')->where('id', $other->id)->update(['permit_number' => 'TMA-HV-2019-000001']),
            '23000',
        );

        expect(Permit::where('permit_number', 'TMA-HV-2019-000001')->count())->toBe(1);
    });

    it('lets any number of permits go without a number', function () {
        Permit::factory()->count(3)->create();

        expect(Permit::whereNull('permit_number')->count())->toBe(3);
    });

    it('keeps a vehicle that is on a permit from being deleted', function () {
        $vehicle = Vehicle::factory()->create();
        Permit::factory()->forVehicles($vehicle)->create();

        expectDatabaseToRefuse(fn () => DB::table('vehicles')->where('id', $vehicle->id)->delete(), '23000');

        expect(Vehicle::whereKey($vehicle->id)->exists())->toBeTrue();
    });

    it('keeps an organization and an applicant that have permits from being deleted', function () {
        $permit = Permit::factory()->withoutVehicles()->create();

        expectDatabaseToRefuse(fn () => DB::table('users')->where('id', $permit->applicant_id)->delete(), '23000');
        expectDatabaseToRefuse(fn () => DB::table('organizations')->where('id', $permit->organization_id)->delete(), '23000');

        expect(Permit::count())->toBe(1);
    });

    it('starts a permit as a draft in Ethiopian birr', function () {
        $permit = Permit::factory()->withoutVehicles()->create();

        $stored = DB::table('permits')->where('id', $permit->id)->first();

        expect($stored->status)->toBe('draft')
            ->and($stored->currency)->toBe('ETB')
            ->and($stored->permit_number)->toBeNull()
            ->and($stored->fee_cents)->toBeNull();
    });
});
