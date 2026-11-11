<?php

use App\Actions\Permits\RemindExpiringPermits;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\PermitExpiringSoon;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/*
 * permits:notify-expiring (spec §11): a reminder a few days before an issued
 * permit's last day, once per permit, to its organization.
 */

beforeEach(function () {
    Notification::fake();
    config(['permits.notifications.expiring_days' => 7]);

    $this->member = applicantWithOrganization(['phone' => '0911223344']);
    $this->vehicle = Vehicle::factory()->for($this->member->organization)->create();
});

function permitOfMemberEnding(User $member, int $daysFromToday, string $state = 'issued'): Permit
{
    $today = ValidityRules::today();

    return Permit::factory()
        ->forVehicles(Vehicle::factory()->for($member->organization)->create())
        ->{$state}()
        ->validBetween($today->subDays(10), $today->addDays($daysFromToday))
        ->create();
}

it('reminds about issued permits ending within the window, today included', function (int $days, bool $reminded) {
    $permit = permitOfMemberEnding($this->member, $days);

    app(RemindExpiringPermits::class)->handle();

    if ($reminded) {
        Notification::assertSentTo($this->member, PermitExpiringSoon::class, fn (PermitExpiringSoon $n): bool => $n->permit->is($permit));
        expect($permit->refresh()->expiry_reminded_at)->not->toBeNull();
    } else {
        Notification::assertNothingSent();
        expect($permit->refresh()->expiry_reminded_at)->toBeNull();
    }
})->with([
    'ends today' => [0, true],
    'ends in 7 days' => [7, true],
    'ends in 8 days' => [8, false],
    'ended yesterday' => [-1, false],
]);

it('also sends an SMS to the organization\'s phone', function () {
    permitOfMemberEnding($this->member, 3);

    app(RemindExpiringPermits::class)->handle();

    Notification::assertSentOnDemand(PermitExpiringSoon::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['sms'] === '0911223344');
});

it('reminds only once per permit', function () {
    permitOfMemberEnding($this->member, 3);

    expect(app(RemindExpiringPermits::class)->handle())->toBe(1)
        ->and(app(RemindExpiringPermits::class)->handle())->toBe(0);

    Notification::assertSentToTimes($this->member, PermitExpiringSoon::class, 1);
});

it('does not remind about permits that are not issued', function (string $state) {
    permitOfMemberEnding($this->member, 3, $state);

    app(RemindExpiringPermits::class)->handle();

    Notification::assertNothingSent();
})->with(['revoked', 'awaitingPayment']);

it('does not remind about a permit marked expired', function () {
    $permit = permitOfMemberEnding($this->member, 3);
    $permit->forceFill(['status' => PermitStatus::Expired])->save();

    app(RemindExpiringPermits::class)->handle();

    Notification::assertNothingSent();
});

it('runs as an artisan command', function () {
    permitOfMemberEnding($this->member, 2);

    $this->artisan('permits:notify-expiring')
        ->expectsOutput('Sent reminders for 1 permit(s).')
        ->assertSuccessful();
});
