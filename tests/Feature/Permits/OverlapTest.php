<?php

use App\Actions\Permits\ApprovePermit;
use App\Actions\Permits\RevokePermit;
use App\Actions\Permits\SubmitPermit;
use App\Domain\Permits\OverlapGuard;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Exceptions\OverlappingPermit;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Spec §4.1: a vehicle may not be on two blocking permits (awaiting payment,
 * payment submitted, issued) whose dates overlap. The Action checks it, and
 * the triggers on permit_vehicles are the final authority.
 */

beforeEach(function () {
    withFeeSchedule();
    $this->applicant = applicantWithOrganization();
    $this->vehicle = Vehicle::factory()->for($this->applicant->organization)->withPlate('ኢት-03-40134')->create();
    $this->approver = userWithRole(Role::Approver);
    $this->today = ValidityRules::today();
});

/**
 * An application under review with a recommendation, ready to be approved,
 * for day offsets counted from today.
 */
function approvablePermit(Vehicle $primary, int $from, int $to, Vehicle ...$trailers): Permit
{
    $today = ValidityRules::today();

    return Permit::factory()
        ->forVehicles($primary, ...$trailers)
        ->recommended()
        ->validBetween($today->addDays($from), $today->addDays($to))
        ->create();
}

/**
 * Runs the statement in a savepoint (a failed statement would otherwise
 * poison the transaction the test runs in) and asserts the database refuses
 * it with the given SQLSTATE.
 */
function expectSqlState(Closure $statement, string $sqlState): void
{
    try {
        DB::transaction($statement);
    } catch (QueryException $exception) {
        expect($exception->getCode())->toBe($sqlState);

        return;
    }

    test()->fail("Expected the database to refuse the statement with SQLSTATE {$sqlState}.");
}

/**
 * A row of permit_vehicles written by hand.
 *
 * @return array<string, mixed>
 */
function permitVehicleRow(Permit $permit, Vehicle $vehicle, string $from, string $to, bool $blocking, string $role = 'primary'): array
{
    return [
        'permit_id' => $permit->id,
        'vehicle_id' => $vehicle->id,
        'role' => $role,
        'period_from' => $from, 'period_to' => $to,
        'is_blocking' => $blocking,
    ];
}

describe('at approval', function () {
    it('refuses to approve when the vehicle is reserved by another permit for overlapping days', function (string $state) {
        $existing = Permit::factory()->forVehicles($this->vehicle)->{$state}()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, 15, 25);

        try {
            app(ApprovePermit::class)->handle($permit, $this->approver);
            $this->fail('The overlapping permit was approved.');
        } catch (OverlappingPermit $exception) {
            expect($exception->plate)->toBe('ኢት-03-40134')
                ->and($exception->from?->toDateString())->toBe($existing->valid_from->toDateString())
                ->and($exception->to?->toDateString())->toBe($existing->valid_to->toDateString());
        }

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->permit_number)->toBeNull()
            ->and($permit->approved_by)->toBeNull()
            ->and($permit->transitions()->count())->toBe(0)
            ->and(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([false])
            ->and(DB::table('permit_number_sequences')->count())->toBe(0);
    })->with(['awaitingPayment', 'paymentSubmitted', 'issued']);

    it('counts every kind of overlap, the first and the last day included', function (int $from, int $to) {
        Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, $from, $to);

        expect(fn () => app(ApprovePermit::class)->handle($permit, $this->approver))
            ->toThrow(OverlappingPermit::class);

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    })->with([
        'ends on the first reserved day' => [5, 10],
        'starts on the last reserved day' => [20, 30],
        'inside the reserved period' => [12, 14],
        'around the reserved period' => [5, 30],
        'the same days' => [10, 20],
        'a single shared day' => [20, 20],
    ]);

    it('approves a permit whose days only touch those of another', function (int $from, int $to) {
        Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, $from, $to);

        app(ApprovePermit::class)->handle($permit, $this->approver);

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([true]);
    })->with([
        'the day after the other ends' => [21, 30],
        'the day before the other starts' => [5, 9],
    ]);

    it('ignores permits that do not reserve their vehicles', function (PermitStatus $status) {
        Permit::factory()->forVehicles($this->vehicle)
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create(['status' => $status]);
        $permit = approvablePermit($this->vehicle, 10, 20);

        app(ApprovePermit::class)->handle($permit, $this->approver);

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    })->with([
        PermitStatus::Draft,
        PermitStatus::Submitted,
        PermitStatus::UnderReview,
        PermitStatus::ChangesRequested,
        PermitStatus::Rejected,
        PermitStatus::Expired,
        PermitStatus::Revoked,
        PermitStatus::Withdrawn,
    ]);

    it('ignores other vehicles with the same dates', function () {
        Permit::factory()->forVehicles(Vehicle::factory()->for($this->applicant->organization)->create())->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, 10, 20);

        app(ApprovePermit::class)->handle($permit, $this->approver);

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    });

    it('counts a trailer that is reserved as the primary vehicle of another permit', function () {
        $trailer = Vehicle::factory()->for($this->applicant->organization)->withPlate('ኢት-03-A33490')->create();
        Permit::factory()->forVehicles($trailer)->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, 15, 25, $trailer);

        try {
            app(ApprovePermit::class)->handle($permit, $this->approver);
            $this->fail('The overlapping permit was approved.');
        } catch (OverlappingPermit $exception) {
            expect($exception->plate)->toBe('ኢት-03-A33490');
        }

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    });

    it('counts a primary vehicle that is reserved as a trailer of another permit', function () {
        $tractor = Vehicle::factory()->for($this->applicant->organization)->create();
        Permit::factory()->forVehicles($tractor, $this->vehicle)->awaitingPayment()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, 15, 25);

        expect(fn () => app(ApprovePermit::class)->handle($permit, $this->approver))
            ->toThrow(OverlappingPermit::class);

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    });

    it('lets only the first of two competing applications through', function () {
        $first = approvablePermit($this->vehicle, 10, 20);
        $second = approvablePermit($this->vehicle, 20, 30);

        app(ApprovePermit::class)->handle($first, $this->approver);

        expect(fn () => app(ApprovePermit::class)->handle($second, $this->approver))
            ->toThrow(OverlappingPermit::class);

        expect($first->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($second->refresh()->status)->toBe(PermitStatus::UnderReview)
            // The refused approval did not use up a number.
            ->and(DB::table('permit_number_sequences')->sum('last_number'))->toEqual(1);
    });

    it('frees the vehicle when the other permit is revoked', function () {
        $issued = Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, 10, 20);

        expect(fn () => app(ApprovePermit::class)->handle($permit, $this->approver))
            ->toThrow(OverlappingPermit::class);

        app(RevokePermit::class)->handle($issued, $this->approver, 'የተሳሳተ መረጃ');
        app(ApprovePermit::class)->handle($permit, $this->approver);

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    });

    it('turns an overlap that only the database saw into the same exception', function () {
        // A guard that misses the conflict while approving, as it would in a race.
        app()->instance(OverlapGuard::class, new class extends OverlapGuard
        {
            public function ensureNoOverlap(Permit $permit, bool $lock = false): void
            {
                if (! $lock) {
                    parent::ensureNoOverlap($permit);
                }
            }
        });

        Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, 15, 25);

        try {
            app(ApprovePermit::class)->handle($permit, $this->approver);
            $this->fail('The overlapping permit was approved.');
        } catch (OverlappingPermit $exception) {
            expect($exception->plate)->toBe('ኢት-03-40134');
        }

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->permit_number)->toBeNull()
            ->and($permit->transitions()->count())->toBe(0)
            ->and(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([false])
            ->and(DB::table('permit_number_sequences')->count())->toBe(0);
    });
});

describe('at submission', function () {
    it('tells the applicant early that a vehicle is already reserved', function () {
        Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $draft = Permit::factory()->forVehicles($this->vehicle)->draft()
            ->validBetween($this->today->addDays(20), $this->today->addDays(22))
            ->create();

        try {
            app(SubmitPermit::class)->handle($draft, $this->applicant);
            $this->fail('The overlapping application was submitted.');
        } catch (OverlappingPermit $exception) {
            expect($exception->plate)->toBe('ኢት-03-40134')
                ->and($exception->userMessage())->toContain('ኢት-03-40134');
        }

        expect($draft->refresh()->status)->toBe(PermitStatus::Draft)
            ->and($draft->submitted_at)->toBeNull();
    });

    it('lets two applications for the same vehicle and days wait side by side', function () {
        $first = Permit::factory()->forVehicles($this->vehicle)->submitted()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $second = Permit::factory()->forVehicles($this->vehicle)->draft()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();

        app(SubmitPermit::class)->handle($second, $this->applicant);

        expect($second->refresh()->status)->toBe(PermitStatus::Submitted)
            ->and($first->refresh()->status)->toBe(PermitStatus::Submitted);
    });
});

describe('the guard', function () {
    it('lists every reserving permit that overlaps, and nothing else', function () {
        $trailer = Vehicle::factory()->for($this->applicant->organization)->create();
        $onPrimary = Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $onTrailer = Permit::factory()->forVehicles($trailer)->awaitingPayment()
            ->validBetween($this->today->addDays(24), $this->today->addDays(40))
            ->create();
        // Not listed: outside the dates, and not reserving.
        Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($this->today->addDays(26), $this->today->addDays(30))
            ->create();
        Permit::factory()->forVehicles($this->vehicle)->submitted()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();

        $permit = approvablePermit($this->vehicle, 15, 25, $trailer);

        $conflicts = app(OverlapGuard::class)->conflicts($permit);

        expect($conflicts)->toHaveCount(2)
            ->and($conflicts[0]->permit_id)->toBe($onPrimary->id)
            ->and($conflicts[0]->vehicle_id)->toBe($this->vehicle->id)
            ->and($conflicts[0]->plate)->toBe('ኢት-03-40134')
            ->and($conflicts[1]->permit_id)->toBe($onTrailer->id)
            ->and($conflicts[1]->vehicle_id)->toBe($trailer->id);
    });

    it('never reports a permit as overlapping itself', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->issued()->create();

        app(OverlapGuard::class)->ensureNoOverlap($permit, lock: true);

        expect(app(OverlapGuard::class)->conflicts($permit))->toBeEmpty();
    });

    it('explains an overlap without naming the other permit or its organization', function () {
        $other = Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($this->today->addDays(10), $this->today->addDays(20))
            ->create();
        $permit = approvablePermit($this->vehicle, 15, 25);

        try {
            app(OverlapGuard::class)->ensureNoOverlap($permit);
            $this->fail('The overlap was not found.');
        } catch (OverlappingPermit $exception) {
            expect($exception->userMessage())
                ->toContain('ኢት-03-40134')
                ->not->toContain($other->permit_number)
                ->not->toContain($other->id)
                ->and((new OverlappingPermit)->userMessage())->toBe(__('permits.errors.overlap_generic'));
        }
    });
});

describe('the database triggers', function () {
    beforeEach(function () {
        $this->first = Permit::factory()->withoutVehicles()->create();
        $this->second = Permit::factory()->withoutVehicles()->create();

        DB::table('permit_vehicles')->insert(
            permitVehicleRow($this->first, $this->vehicle, '2027-01-10', '2027-01-20', blocking: true),
        );
    });

    it('refuses a second reserving row for the same vehicle and overlapping days', function (string $from, string $to) {
        expectSqlState(fn () => DB::table('permit_vehicles')->insert(
            permitVehicleRow($this->second, $this->vehicle, $from, $to, blocking: true),
        ), '45000');

        expect(DB::table('permit_vehicles')->count())->toBe(1);
    })->with([
        'overlapping' => ['2027-01-15', '2027-01-25'],
        'sharing the last day' => ['2027-01-20', '2027-01-30'],
        'sharing the first day' => ['2027-01-01', '2027-01-10'],
        'inside' => ['2027-01-12', '2027-01-13'],
    ]);

    it('refuses it whatever the role of the vehicle', function () {
        expectSqlState(fn () => DB::table('permit_vehicles')->insert(
            permitVehicleRow($this->second, $this->vehicle, '2027-01-15', '2027-01-25', blocking: true, role: 'trailer'),
        ), '45000');
    });

    it('accepts adjacent days, another vehicle, and a row that does not reserve', function () {
        $third = Permit::factory()->withoutVehicles()->create();
        $fourth = Permit::factory()->withoutVehicles()->create();

        DB::table('permit_vehicles')->insert([
            permitVehicleRow($this->second, $this->vehicle, '2027-01-21', '2027-01-30', blocking: true),
            permitVehicleRow($third, Vehicle::factory()->create(), '2027-01-10', '2027-01-20', blocking: true),
            permitVehicleRow($fourth, $this->vehicle, '2027-01-10', '2027-01-20', blocking: false),
        ]);

        expect(DB::table('permit_vehicles')->count())->toBe(4);
    });

    it('refuses to turn an overlapping row into a reserving one', function () {
        DB::table('permit_vehicles')->insert(
            permitVehicleRow($this->second, $this->vehicle, '2027-01-15', '2027-01-25', blocking: false),
        );

        expectSqlState(fn () => DB::table('permit_vehicles')
            ->where('permit_id', $this->second->id)
            ->update(['is_blocking' => true]), '45000');

        expect(DB::table('permit_vehicles')->where('permit_id', $this->second->id)->value('is_blocking'))->toBeFalsy();
    });

    it('refuses to move a reserving row onto days that are taken', function () {
        DB::table('permit_vehicles')->insert(
            permitVehicleRow($this->second, $this->vehicle, '2027-02-01', '2027-02-10', blocking: true),
        );

        expectSqlState(fn () => DB::table('permit_vehicles')
            ->where('permit_id', $this->second->id)
            ->update(['period_from' => '2027-01-20']), '45000');

        expect(permitVehicleRows($this->second)->first()->period_from)->toBe('2027-02-01');
    });

    it('accepts the days again once the first row stops reserving', function () {
        DB::table('permit_vehicles')->where('permit_id', $this->first->id)->update(['is_blocking' => false]);

        DB::table('permit_vehicles')->insert(
            permitVehicleRow($this->second, $this->vehicle, '2027-01-15', '2027-01-25', blocking: true),
        );

        expect(DB::table('permit_vehicles')->where('is_blocking', true)->count())->toBe(1);
    });
});
