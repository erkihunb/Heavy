<?php

use App\Enums\Role;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\PaymentRejected;
use App\Notifications\PermitApproved;
use App\Notifications\PermitIssued;
use App\Notifications\PermitRevoked;
use Illuminate\Notifications\AnonymousNotifiable;

/*
 * What the messages say (spec §11): in the recipient's language, naming the
 * permit and what happened; SMS short and free of fees, receipts and links.
 */

beforeEach(function () {
    $this->permit = Permit::factory()
        ->forVehicles(Vehicle::factory()->create())
        ->awaitingPayment()
        ->validBetween('2025-10-08', '2025-10-17')
        ->create(['permit_number' => 'TMA-HV-2018-000123', 'fee_cents' => 600_050]);
});

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function inLocale(string $locale, callable $callback): mixed
{
    $previous = app()->getLocale();
    app()->setLocale($locale);

    try {
        return $callback();
    } finally {
        app()->setLocale($previous);
    }
}

function mailFor(object $notification, User $user): string
{
    // As the notification sender does: in the recipient's preferred language.
    return (string) inLocale($user->preferredLocale(), fn () => $notification->toMail($user)->render());
}

it('writes the e-mail in English for an English-speaking member', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $html = mailFor(new PermitApproved($this->permit), $user);
    $subject = inLocale('en', fn () => (new PermitApproved($this->permit))->toMail($user)->subject);

    expect($subject)->toBe('Permit TMA-HV-2018-000123 approved — please pay the fee')
        ->and($html)
        ->toContain('TMA-HV-2018-000123')
        ->toContain('ETB 6,000.50')
        ->toContain('Meskerem 28, 2018')
        ->toContain(route('permits.show', $this->permit));
});

it('writes the e-mail in Amharic for an Amharic-speaking member', function () {
    $user = User::factory()->create(['locale' => 'am']);

    $html = mailFor(new PermitApproved($this->permit), $user);
    $subject = inLocale('am', fn () => (new PermitApproved($this->permit))->toMail($user)->subject);

    expect($subject)->toStartWith('ፍቃድ TMA-HV-2018-000123 ጸድቋል')
        ->and($html)
        ->toContain('በፍቃድ ቁጥር TMA-HV-2018-000123 ጸድቋል')
        ->toContain('መስከረም 28, 2018');
});

it('uses each user\'s language preference, falling back to the default', function () {
    expect(User::factory()->make(['locale' => 'en'])->preferredLocale())->toBe('en')
        ->and(User::factory()->make(['locale' => 'fr'])->preferredLocale())->toBe(config('app.locale'))
        ->and(User::factory()->make(['locale' => null])->preferredLocale())->toBe(config('app.locale'));
});

it('passes on the Authority\'s note', function () {
    $user = User::factory()->create(['locale' => 'en']);

    expect(mailFor(new PermitRevoked($this->permit, 'Forged receipt.'), $user))
        ->toContain('Note from the Authority: Forged receipt.');
});

it('keeps SMS short and free of fees, receipts and links', function (string $class) {
    $sms = inLocale('am', fn () => (new $class($this->permit, 'Receipt FS-228740 is not readable.'))->toSms(new AnonymousNotifiable));

    expect(mb_strlen($sms))->toBeLessThanOrEqual(160)
        ->and($sms)->toContain('TMA-HV-2018-000123')
        ->not->toContain('6,000')
        ->not->toContain('FS-228740')
        ->not->toContain('http');
})->with([PermitApproved::class, PermitIssued::class, PermitRevoked::class, PaymentRejected::class]);

it('has every message in both languages', function () {
    foreach (['approved', 'issued', 'revoked', 'expiring_soon', 'changes_requested', 'rejected', 'payment_rejected'] as $key) {
        foreach (['subject', 'body', 'sms'] as $part) {
            foreach (['am', 'en'] as $locale) {
                expect(trans("notifications.{$key}.{$part}", [], $locale))->not->toBe("notifications.{$key}.{$part}");
            }
        }
    }
});

it('never names staff in a message', function () {
    $approver = userWithRole(Role::Approver);
    $this->permit->forceFill(['approved_by' => $approver->id])->save();
    $user = User::factory()->create(['locale' => 'en']);

    expect(mailFor(new PermitApproved($this->permit), $user))->not->toContain($approver->name);
});
