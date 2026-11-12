<?php

use App\Actions\Permits\ApprovePermit;
use App\Domain\Permits\PermitNumberGenerator;
use App\Enums\PermitType;
use App\Enums\Role;
use App\Exceptions\OverlappingPermit;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * Spec §4.7: permit numbers are "<prefix>-<Ethiopian year>-<six digits>",
 * counted per Ethiopian calendar year and never handed out twice.
 */

beforeEach(function () {
    withFeeSchedule();
    $this->numbers = app(PermitNumberGenerator::class);
    // Noon in Addis Ababa on 1 Tir 2019 and 1 Tir 2020.
    $this->in2019 = EthiopianCalendar::toGregorian(2019, 5, 1)->setTime(9, 0);
    $this->in2020 = EthiopianCalendar::toGregorian(2020, 5, 1)->setTime(9, 0);
});

it('formats the number as prefix, Ethiopian year and six digits', function () {
    expect($this->numbers->next($this->in2019))->toBe('AACRA-2019-000001');
});

it('uses the Ethiopian year of the given moment, not the Gregorian one', function () {
    // 11 November 2026 is in the Ethiopian year 2019.
    expect($this->numbers->next(CarbonImmutable::parse('2026-11-11 09:00', 'UTC')))->toBe('AACRA-2019-000001');
});

it('counts up without gaps within a year', function () {
    $numbers = array_map(fn () => $this->numbers->next($this->in2019), range(1, 12));

    expect($numbers[0])->toBe('AACRA-2019-000001')
        ->and($numbers[1])->toBe('AACRA-2019-000002')
        ->and($numbers[9])->toBe('AACRA-2019-000010')
        ->and($numbers[11])->toBe('AACRA-2019-000012')
        ->and(array_unique($numbers))->toHaveCount(12)
        ->and(DB::table('permit_number_sequences')->where('year', 2019)->value('last_number'))->toBe(12);
});

it('starts again at one in a new Ethiopian year and keeps each year apart', function () {
    $this->numbers->next($this->in2019);
    $this->numbers->next($this->in2019);

    expect($this->numbers->next($this->in2020))->toBe('AACRA-2020-000001')
        ->and($this->numbers->next($this->in2020))->toBe('AACRA-2020-000002')
        // The older year carries on where it was.
        ->and($this->numbers->next($this->in2019))->toBe('AACRA-2019-000003')
        ->and(DB::table('permit_number_sequences')->orderBy('year')->pluck('last_number', 'year')->all())
        ->toBe([2019 => 3, 2020 => 2]);
});

it('changes year at Ethiopian new year, by the calendar day in Addis Ababa', function () {
    // 1 Meskerem 2020, as midnight UTC of that Gregorian day.
    $newYear = EthiopianCalendar::toGregorian(2020, 1, 1);

    // 23:00 in Addis Ababa on the last day of 2019.
    expect($this->numbers->next($newYear->subHours(4)))->toBe('AACRA-2019-000001')
        // 01:00 in Addis Ababa on new year's day — still the day before in UTC.
        ->and($this->numbers->next($newYear->subHours(2)))->toBe('AACRA-2020-000001')
        ->and($this->numbers->next($newYear->addHours(12)))->toBe('AACRA-2020-000002');
});

it('takes the prefix from the configuration', function () {
    config(['permits.number_prefix' => 'XYZ']);

    expect($this->numbers->next($this->in2019))->toBe('XYZ-2019-000001');
});

it('follows the number rule from the settings', function () {
    config(['permits.number_prefix' => 'AA', 'permits.number_format' => '{year}-{prefix}-{seq}', 'permits.number_digits' => 4]);

    expect($this->numbers->next($this->in2019))->toBe('2019-AA-0001')
        ->and($this->numbers->next($this->in2019))->toBe('2019-AA-0002');
});

it('never cuts a counter that outgrows its digits', function () {
    config(['permits.number_digits' => 4]);
    DB::table('permit_number_sequences')->insert(['year' => 2019, 'last_number' => 9999]);

    expect($this->numbers->next($this->in2019))->toBe('AACRA-2019-10000');
});

it('gives the increment back when the surrounding transaction fails', function () {
    $this->numbers->next($this->in2019);

    try {
        DB::transaction(function () {
            $this->numbers->next($this->in2019);

            throw new RuntimeException('The approval failed.');
        });
    } catch (RuntimeException) {
        // The approval was rolled back.
    }

    expect($this->numbers->next($this->in2019))->toBe('AACRA-2019-000002');
});

describe('at approval', function () {
    beforeEach(function () {
        $this->applicant = applicantWithOrganization();
        $this->approver = userWithRole(Role::Approver);
        $this->year = EthiopianCalendar::toEthiopian(
            CarbonImmutable::now(config('localization.display_timezone')),
        )->year;

        $this->approvable = fn (?Vehicle $vehicle = null): Permit => Permit::factory()
            ->forVehicles($vehicle ?? Vehicle::factory()->for($this->applicant->organization)->create())
            ->recommended()
            ->create();
    });

    it('numbers approved permits one after the other in the current Ethiopian year', function () {
        $permits = [($this->approvable)(), ($this->approvable)(), ($this->approvable)()];

        foreach ($permits as $permit) {
            app(ApprovePermit::class)->handle($permit, $this->approver);
        }

        expect(array_map(fn (Permit $permit): ?string => $permit->refresh()->permit_number, $permits))->toBe([
            "AACRA-{$this->year}-000001",
            "AACRA-{$this->year}-000002",
            "AACRA-{$this->year}-000003",
        ]);
    });

    it('leaves no gap when an approval is refused', function () {
        $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
        $first = ($this->approvable)($vehicle);
        $refused = ($this->approvable)($vehicle);
        $third = ($this->approvable)();

        app(ApprovePermit::class)->handle($first, $this->approver);

        expect(fn () => app(ApprovePermit::class)->handle($refused, $this->approver))
            ->toThrow(OverlappingPermit::class);

        app(ApprovePermit::class)->handle($third, $this->approver);

        expect($first->refresh()->permit_number)->toBe("AACRA-{$this->year}-000001")
            ->and($refused->refresh()->permit_number)->toBeNull()
            ->and($third->refresh()->permit_number)->toBe("AACRA-{$this->year}-000002");
    });

    it('never gives two permits the same number', function () {
        $numbers = collect(range(1, 8))
            ->map(fn (): Permit => ($this->approvable)())
            ->map(fn (Permit $permit): ?string => app(ApprovePermit::class)->handle($permit, $this->approver)->permit_number);

        expect($numbers->unique())->toHaveCount(8)
            ->and($numbers->every(fn (?string $number): bool => preg_match('/^AACRA-\d{4}-\d{6}$/', (string) $number) === 1))->toBeTrue()
            ->and(Permit::query()->whereNotNull('permit_number')->distinct()->count('permit_number'))->toBe(8);
    });
});

describe('code rule options (System settings → Code rules)', function () {
    it('prints the permit type code for {type}', function () {
        config(['permits.number_format' => '{prefix}-{type}-{year}-{seq}', 'permits.number_type_codes.pass' => 'PS']);

        expect($this->numbers->next($this->in2019, null, PermitType::Pass))->toBe('AACRA-PS-2019-000001')
            ->and($this->numbers->next($this->in2019, null, PermitType::FullDay))->toBe('AACRA-FD-2019-000002');
    });

    it('prints the year in two digits when asked', function () {
        config(['permits.number_year_format' => 'short']);

        expect($this->numbers->next($this->in2019))->toBe('AACRA-19-000001');
    });

    it('starts the counter from the first value set', function () {
        config(['permits.number_start' => 500]);

        expect($this->numbers->next($this->in2019))->toBe('AACRA-2019-000500')
            ->and($this->numbers->next($this->in2019))->toBe('AACRA-2019-000501');
    });

    it('keeps counting across years when the counter never restarts', function () {
        config(['permits.number_reset' => 'never']);

        expect($this->numbers->next($this->in2019))->toBe('AACRA-2019-000001')
            ->and($this->numbers->next($this->in2020))->toBe('AACRA-2020-000002');
    });

    it('shows the next number without taking it', function () {
        $this->numbers->next($this->in2019);

        expect($this->numbers->peek($this->in2019))->toBe(2)
            ->and($this->numbers->peek($this->in2019))->toBe(2)
            ->and($this->numbers->next($this->in2019))->toBe('AACRA-2019-000002');
    });
});
