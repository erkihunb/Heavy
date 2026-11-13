<?php

use App\Domain\Permits\MovementHours;
use App\Domain\Permits\PermitVerifier;
use App\Domain\Permits\VerificationTokenService;
use App\Enums\Role;
use App\Enums\ScanResult;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VerificationScan;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Movement hours (owner's decision, 2026-11-12): each permit may let its
 * vehicles move only between two times of the day. Checked outside them, a
 * permit that is in force today says the vehicle may not move now.
 */

/**
 * A moment today in Addis Ababa time.
 */
function addisAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse('today '.$time, (string) config('localization.display_timezone'));
}

describe('the hours themselves', function () {
    it('lets the vehicle move from the start up to, not including, the end', function () {
        $hours = MovementHours::from('11:00', '14:00');

        expect($hours->allows(addisAt('10:59')))->toBeFalse()
            ->and($hours->allows(addisAt('11:00')))->toBeTrue()
            ->and($hours->allows(addisAt('13:59')))->toBeTrue()
            ->and($hours->allows(addisAt('14:00')))->toBeFalse()
            ->and($hours->allows(addisAt('20:00')))->toBeFalse();
    });

    it('reads the clock in Addis Ababa, whatever the server timezone', function () {
        $hours = MovementHours::from('11:00', '14:00');

        // 09:00 UTC is 12:00 in Addis Ababa.
        expect($hours->allows(CarbonImmutable::parse('today 09:00', 'UTC')))->toBeTrue()
            ->and($hours->allows(CarbonImmutable::parse('today 12:00', 'UTC')))->toBeFalse();
    });

    it('may run past midnight', function () {
        $hours = MovementHours::from('20:00', '06:00');

        expect($hours->allows(addisAt('22:00')))->toBeTrue()
            ->and($hours->allows(addisAt('03:00')))->toBeTrue()
            ->and($hours->allows(addisAt('06:00')))->toBeFalse()
            ->and($hours->allows(addisAt('12:00')))->toBeFalse();
    });

    it('reads a time on the Ethiopian clock', function (string $time, string $hour, string $period) {
        expect(MovementHours::ethiopian($time))->toBe(['hour' => $hour, 'period' => $period]);
    })->with([
        ['11:00', '5:00', 'day'],
        ['14:00', '8:00', 'day'],
        ['06:00', '12:00', 'day'],
        ['18:30', '12:30', 'evening'],
        ['02:00', '8:00', 'night'],
    ]);

    it('says the window in both clocks', function () {
        $hours = MovementHours::from('11:00', '14:00');

        expect($hours->label('am'))->toBe('ከቀኑ 5:00 – ከቀኑ 8:00 (11:00–14:00)')
            ->and($hours->label('en'))->toContain('11:00–14:00');
    });

    it('means any time of day without both times', function () {
        expect(MovementHours::from(null, '14:00'))->toBeNull()
            ->and(MovementHours::from('', ''))->toBeNull();
    });
});

describe('a check outside the hours', function () {
    beforeEach(function () {
        ['token' => $this->token, 'hash' => $hash] = app(VerificationTokenService::class)->generate();

        $this->permit = Permit::factory()->active()->create([
            'verification_token_hash' => $hash,
            'move_from' => '11:00',
            'move_to' => '14:00',
        ]);
    });

    afterEach(fn () => CarbonImmutable::setTestNow());

    it('says the vehicle may not move now, with its hours and the time', function () {
        CarbonImmutable::setTestNow(addisAt('09:30'));

        $this->withCookie('locale', 'am')
            ->get(route('verification.token', $this->token))
            ->assertOk()
            ->assertSee('data-result="outside_hours"', false)
            ->assertSee('አሁን አይፈቀድም')
            ->assertSee('ከቀኑ 5:00 – ከቀኑ 8:00 (11:00–14:00)')
            ->assertSee('ከቀኑ 3:30 (09:30)');

        expect(VerificationScan::query()->sole()->result)->toBe(ScanResult::OutsideHours);
    });

    it('is valid inside the hours, and shows them', function () {
        CarbonImmutable::setTestNow(addisAt('12:00'));

        $this->get(route('verification.token', $this->token))
            ->assertSee('data-result="valid"', false)
            ->assertSee('11:00–14:00');
    });

    it('lets a permit for any time of day be valid at night', function () {
        $this->permit->forceFill(['move_from' => null, 'move_to' => null])->save();
        CarbonImmutable::setTestNow(addisAt('03:00'));

        expect(app(PermitVerifier::class)->result($this->permit->refresh()))->toBe(ScanResult::Valid);
    });

    it('still says expired or revoked, whatever the hour', function () {
        CarbonImmutable::setTestNow(addisAt('09:30'));
        $this->permit->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();

        expect(app(PermitVerifier::class)->result($this->permit->refresh()))->toBe(ScanResult::Revoked);
    });

    it('tells the traffic officer too', function () {
        CarbonImmutable::setTestNow(addisAt('15:00'));

        $this->actingAs(staffUser(Role::TrafficOfficer))
            ->get(route('verification.token', $this->token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('officer/scan')
                ->where('verification.result', 'outside_hours')
                ->where('verification.permit.hours.from', '11:00')
                ->where('verification.permit.hours.to', '14:00'));
    });
});

describe('entering the hours on an application', function () {
    beforeEach(function () {
        // The Registrar enters the hours from the letter, for the applicant.
        $this->registrar = staffUser(Role::Registrar);
        $this->applicant = applicantWithOrganization();
    });

    it('saves a window', function () {
        $this->actingAs($this->registrar)
            ->post(route('admin.permits.store'), requestFor($this->applicant, ['any_time' => false, 'move_from' => '11:00', 'move_to' => '14:00']))
            ->assertSessionHasNoErrors();

        expect(Permit::query()->sole()->movementHours()?->present('en'))->toMatchArray(['from' => '11:00', 'to' => '14:00']);
    });

    it('saves any time of day for a full-day permit', function () {
        $this->actingAs($this->registrar)
            ->post(route('admin.permits.store'), requestFor($this->applicant, ['any_time' => true]))
            ->assertSessionHasNoErrors();

        expect(Permit::query()->sole()->movementHours())->toBeNull();
    });

    it('validates the hours', function (array $hours, string $field) {
        $this->actingAs($this->registrar)
            ->post(route('admin.permits.store'), requestFor($this->applicant, $hours))
            ->assertSessionHasErrors($field);
    })->with([
        'no start' => [['any_time' => false, 'move_to' => '14:00'], 'move_from'],
        'no end' => [['any_time' => false, 'move_from' => '11:00'], 'move_to'],
        'not a time' => [['any_time' => false, 'move_from' => '5 o\'clock', 'move_to' => '14:00'], 'move_from'],
        'same times' => [['any_time' => false, 'move_from' => '11:00', 'move_to' => '11:00'], 'move_to'],
    ]);

    it('takes the hours on the office one-page form too', function () {
        $type = VehicleType::factory()->create();

        $this->actingAs(staffUser(Role::Registrar))
            ->post(route('admin.permits.store'), [
                'contact_name' => 'Abebe Kebede',
                'contact_email' => 'abebe@example.test',
                'locale' => 'am',
                'name_am' => 'አበበ',
                'phone' => '0911223344',
                'email' => 'office@abebe.test',
                'address' => 'Bole',
                'apply' => true,
                'submit' => false,
                'vehicles' => [['plate' => 'ኢት-03-40134', 'vehicle_type_id' => $type->id, 'owner_name' => 'አበበ']],
                'permit_type' => 'pass',
                'valid_from' => now()->addDays(3)->toDateString(),
                'valid_to' => now()->addDays(5)->toDateString(),
                'reason' => 'ጭነት',
                'any_time' => false,
                'move_from' => '20:00',
                'move_to' => '06:00',
                ...letterFields(),
            ])
            ->assertSessionHasNoErrors();

        expect(Permit::query()->sole()->movementHours()?->label('am'))->toBe('ከምሽቱ 2:00 – ከቀኑ 12:00 (20:00–06:00)');
    });
});

it('shows the hours with the permit details', function () {
    $applicant = applicantWithOrganization();
    $vehicle = Vehicle::factory()->for($applicant->organization)->create();
    $permit = Permit::factory()->forVehicles($vehicle)->draft()->create(['move_from' => '11:00', 'move_to' => '14:00', 'applicant_id' => $applicant->id]);

    $this->actingAs(staffUser(Role::Registrar))
        ->get(route('admin.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page->where('permit.hours.from', '11:00')->where('permit.hours.to', '14:00'));
});
