<?php

use App\Actions\Permits\ExpirePermits;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/*
 * permits:expire (spec §4.9): issued permits whose last day has passed become
 * expired, with a history row and their vehicles freed.
 */

function issuedPermitEnding(int $daysFromToday, ?Vehicle $vehicle = null): Permit
{
    $today = ValidityRules::today();
    $factory = Permit::factory()->issued()->validBetween($today->subDays(20), $today->addDays($daysFromToday));

    return ($vehicle ? $factory->forVehicles($vehicle) : $factory)->create();
}

it('expires issued permits whose last day was before today', function () {
    $endedYesterday = issuedPermitEnding(-1);
    $endedLongAgo = issuedPermitEnding(-15);
    $endsToday = issuedPermitEnding(0);
    $endsLater = issuedPermitEnding(5);

    expect(app(ExpirePermits::class)->handle())->toBe(2);

    expect($endedYesterday->refresh()->status)->toBe(PermitStatus::Expired)
        ->and($endedLongAgo->refresh()->status)->toBe(PermitStatus::Expired)
        // The last day still counts.
        ->and($endsToday->refresh()->status)->toBe(PermitStatus::Issued)
        ->and($endsLater->refresh()->status)->toBe(PermitStatus::Issued);
});

it('leaves permits in every other status alone', function (string $state) {
    $today = ValidityRules::today();
    $permit = Permit::factory()->{$state}()->validBetween($today->subDays(20), $today->subDays(2))->create();
    $before = $permit->status;

    app(ExpirePermits::class)->handle();

    expect($permit->refresh()->status)->toBe($before);
})->with(['revoked', 'awaitingPayment', 'paymentSubmitted', 'submitted', 'draft']);

it('records the expiry in the history without an actor', function () {
    $permit = issuedPermitEnding(-1);

    app(ExpirePermits::class)->handle();

    $step = PermitStatusTransition::query()->where('permit_id', $permit->id)->sole();

    expect($step->from_status)->toBe(PermitStatus::Issued)
        ->and($step->to_status)->toBe(PermitStatus::Expired)
        ->and($step->actor_id)->toBeNull();
});

it('frees the vehicles for new permits', function () {
    $vehicle = Vehicle::factory()->create();
    $permit = issuedPermitEnding(-1, $vehicle);

    expect((bool) DB::table('permit_vehicles')->where('permit_id', $permit->id)->value('is_blocking'))->toBeTrue();

    app(ExpirePermits::class)->handle();

    expect((bool) DB::table('permit_vehicles')->where('permit_id', $permit->id)->value('is_blocking'))->toBeFalse();
});

it('does nothing the second time', function () {
    issuedPermitEnding(-1);

    expect(app(ExpirePermits::class)->handle())->toBe(1)
        ->and(app(ExpirePermits::class)->handle())->toBe(0)
        ->and(PermitStatusTransition::query()->count())->toBe(1);
});

it('handles more permits than one chunk', function () {
    $today = ValidityRules::today();
    Permit::factory()->count(205)->issued()->validBetween($today->subDays(20), $today->subDay())->create();

    expect(app(ExpirePermits::class)->handle())->toBe(205)
        ->and(Permit::query()->where('status', PermitStatus::Issued)->count())->toBe(0);
});

it('runs as an artisan command', function () {
    issuedPermitEnding(-1);

    $this->artisan('permits:expire')
        ->expectsOutput('Expired 1 permit(s).')
        ->assertSuccessful();
});

it('follows the Addis Ababa calendar day, not UTC', function () {
    $permit = issuedPermitEnding(0);

    // 22:30 UTC is already 01:30 the next day in Addis Ababa.
    $this->travelTo(now(config('localization.display_timezone'))->endOfDay()->subMinutes(30)->utc()->addHours(3));

    app(ExpirePermits::class)->handle();

    expect($permit->refresh()->status)->toBe(PermitStatus::Expired);
});
