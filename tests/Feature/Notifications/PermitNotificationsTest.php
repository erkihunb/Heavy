<?php

use App\Actions\Permits\ApprovePermit;
use App\Actions\Permits\IssuePaidPermit;
use App\Actions\Permits\RejectPayment;
use App\Actions\Permits\RejectPermit;
use App\Actions\Permits\RequestChanges;
use App\Actions\Permits\RevokePermit;
use App\Actions\Permits\VerifyPayment;
use App\Enums\Role;
use App\Exceptions\PermitRuleViolation;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ChangesRequested;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\PaymentRejected;
use App\Notifications\PermitApproved;
use App\Notifications\PermitExpiringSoon;
use App\Notifications\PermitIssued;
use App\Notifications\PermitNotification;
use App\Notifications\PermitRejected;
use App\Notifications\PermitRevoked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/*
 * Each decision on a permit tells its organization (spec §11): every verified
 * member by e-mail, the organization's phone by SMS. Queued, after commit.
 */

beforeEach(function () {
    Notification::fake();
    withFeeSchedule();

    $this->member = applicantWithOrganization(['phone' => '0911223344']);
    $this->organization = $this->member->organization;
    $this->colleague = userWithRole(Role::Applicant);
    $this->colleague->forceFill(['organization_id' => $this->organization->id])->save();
    $this->unverified = User::factory()->unverified()->create(['organization_id' => $this->organization->id]);
    $this->unverified->assignRole(Role::Applicant->value);
    $this->outsider = applicantWithOrganization();

    $this->vehicle = Vehicle::factory()->for($this->organization)->create();
    $this->reviewer = userWithRole(Role::Reviewer);
    $this->approver = userWithRole(Role::Approver);
    $this->cashier = userWithRole(Role::Cashier);
});

/**
 * @param  class-string<PermitNotification>  $class
 */
function assertOrganizationNotified(string $class, Permit $permit, ?string $note = null): void
{
    $matches = fn (PermitNotification $n): bool => $n->permit->is($permit) && $n->note === $note;

    Notification::assertSentTo(test()->member, $class, $matches);
    Notification::assertSentTo(test()->colleague, $class, $matches);
    Notification::assertNotSentTo(test()->unverified, $class);
    Notification::assertNotSentTo(test()->outsider, $class);
    Notification::assertNotSentTo([test()->reviewer, test()->approver, test()->cashier], $class);
    Notification::assertSentOnDemand($class, fn ($n, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['sms'] === '0911223344');
}

it('tells the organization when an application is approved, with the fee', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->recommended(reviewer: $this->reviewer)->create();

    app(ApprovePermit::class)->handle($permit, $this->approver);

    assertOrganizationNotified(PermitApproved::class, $permit);
});

it('tells the organization when changes are requested, with the note', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

    app(RequestChanges::class)->handle($permit, $this->reviewer, 'Add the trailer plate.');

    assertOrganizationNotified(ChangesRequested::class, $permit, 'Add the trailer plate.');
});

it('tells the organization when an application is rejected, with the reason', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->recommended(reviewer: $this->reviewer)->create();

    app(RejectPermit::class)->handle($permit, $this->approver, 'Route not allowed for this vehicle.');

    assertOrganizationNotified(PermitRejected::class, $permit, 'Route not allowed for this vehicle.');
});

it('tells the organization when the permit is issued, without the cashier\'s internal remark', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->paymentSubmitted()->create();
    $payment = Payment::factory()->forPermit($permit)->create();

    app(VerifyPayment::class)->handle($payment, $this->cashier, 'Checked against bank statement.');
    // Confirming the payment says nothing yet; issuing does.
    Notification::assertNothingSent();

    app(IssuePaidPermit::class)->handle($permit->refresh(), userWithRole(Role::Issuer));

    assertOrganizationNotified(PermitIssued::class, $permit);
});

it('tells the organization when a receipt is rejected, with the reason', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->paymentSubmitted()->create();
    $payment = Payment::factory()->forPermit($permit)->create();

    app(RejectPayment::class)->handle($payment, $this->cashier, 'Receipt is unreadable.');

    assertOrganizationNotified(PaymentRejected::class, $permit, 'Receipt is unreadable.');
});

it('tells the organization when the permit is revoked, with the reason', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->active()->create();

    app(RevokePermit::class)->handle($permit, $this->approver, 'Forged receipt.');

    assertOrganizationNotified(PermitRevoked::class, $permit, 'Forged receipt.');
});

it('sends nothing when the action is refused', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

    expect(fn () => app(ApprovePermit::class)->handle($permit, $this->approver))->toThrow(PermitRuleViolation::class);

    Notification::assertNothingSent();
});

it('sends no SMS when the organization has no phone, or SMS is switched off', function () {
    Organization::query()->whereKey($this->organization->id)->update(['phone' => '']);
    $permit = Permit::factory()->forVehicles($this->vehicle)->active()->create();

    app(RevokePermit::class)->handle($permit, $this->approver, 'Forged receipt.');

    Notification::assertSentTo($this->member, PermitRevoked::class);
    Notification::assertNotSentTo(new AnonymousNotifiable, PermitRevoked::class);
});

it('can switch SMS off', function () {
    config(['permits.notifications.sms' => false]);
    $permit = Permit::factory()->forVehicles($this->vehicle)->active()->create();

    app(RevokePermit::class)->handle($permit, $this->approver, 'Forged receipt.');

    Notification::assertNotSentTo(new AnonymousNotifiable, PermitRevoked::class);
});

it('queues every permit notification and sends it only after commit', function (string $class) {
    $notification = new $class(Permit::factory()->make());

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->tries)->toBe(3);
})->with([PermitApproved::class, PermitIssued::class, PermitRevoked::class, ChangesRequested::class, PermitRejected::class, PaymentRejected::class, PermitExpiringSoon::class]);

it('reaches users by e-mail and the phone route by SMS', function () {
    $notification = new PermitRevoked(Permit::factory()->make());

    expect($notification->via($this->member))->toBe(['mail'])
        ->and($notification->via(Notification::route('sms', '0911')))->toBe([SmsChannel::class]);
});
