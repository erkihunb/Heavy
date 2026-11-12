<?php

use App\Domain\Permits\PermitStateMachine;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;

/*
 * The lifecycle in spec §3, written out by hand so that a change to the state
 * machine has to be made here as well.
 */
const ALLOWED_TRANSITIONS = [
    'submit' => [PermitStatus::Draft, PermitStatus::Submitted],
    'withdraw a draft' => [PermitStatus::Draft, PermitStatus::Withdrawn],
    'start review' => [PermitStatus::Submitted, PermitStatus::UnderReview],
    'withdraw a submitted application' => [PermitStatus::Submitted, PermitStatus::Withdrawn],
    'request changes' => [PermitStatus::UnderReview, PermitStatus::ChangesRequested],
    'reject' => [PermitStatus::UnderReview, PermitStatus::Rejected],
    'approve' => [PermitStatus::UnderReview, PermitStatus::AwaitingPayment],
    'resubmit' => [PermitStatus::ChangesRequested, PermitStatus::Submitted],
    'submit payment' => [PermitStatus::AwaitingPayment, PermitStatus::PaymentSubmitted],
    'verify payment' => [PermitStatus::PaymentSubmitted, PermitStatus::PaymentConfirmed],
    'record a payment at the desk' => [PermitStatus::AwaitingPayment, PermitStatus::PaymentConfirmed],
    'issue a paid permit' => [PermitStatus::PaymentConfirmed, PermitStatus::Issued],
    'reject payment' => [PermitStatus::PaymentSubmitted, PermitStatus::AwaitingPayment],
    'expire an issued permit' => [PermitStatus::Issued, PermitStatus::Expired],
    'revoke an issued permit' => [PermitStatus::Issued, PermitStatus::Revoked],
    'expire an active permit' => [PermitStatus::Active, PermitStatus::Expired],
    'revoke an active permit' => [PermitStatus::Active, PermitStatus::Revoked],
];

it('allows every transition in the specification', function (PermitStatus $from, PermitStatus $to) {
    $machine = new PermitStateMachine;

    expect($machine->canTransition($from, $to))->toBeTrue()
        ->and(fn () => $machine->assertCanTransition($from, $to))->not->toThrow(InvalidPermitTransition::class);
})->with(ALLOWED_TRANSITIONS);

it('refuses transitions the specification does not list', function (PermitStatus $from, PermitStatus $to) {
    $machine = new PermitStateMachine;

    expect($machine->canTransition($from, $to))->toBeFalse()
        ->and(fn () => $machine->assertCanTransition($from, $to))->toThrow(InvalidPermitTransition::class);
})->with([
    'approve a draft' => [PermitStatus::Draft, PermitStatus::AwaitingPayment],
    'review a draft' => [PermitStatus::Draft, PermitStatus::UnderReview],
    'issue a draft' => [PermitStatus::Draft, PermitStatus::Issued],
    'approve without review' => [PermitStatus::Submitted, PermitStatus::AwaitingPayment],
    'reject without review' => [PermitStatus::Submitted, PermitStatus::Rejected],
    'submit twice' => [PermitStatus::Submitted, PermitStatus::Submitted],
    'withdraw during review' => [PermitStatus::UnderReview, PermitStatus::Withdrawn],
    'issue without payment' => [PermitStatus::UnderReview, PermitStatus::Issued],
    'send back to the queue' => [PermitStatus::UnderReview, PermitStatus::Submitted],
    'withdraw after changes were requested' => [PermitStatus::ChangesRequested, PermitStatus::Withdrawn],
    'review without resubmission' => [PermitStatus::ChangesRequested, PermitStatus::UnderReview],
    'issue before payment' => [PermitStatus::AwaitingPayment, PermitStatus::Issued],
    'reject after approval' => [PermitStatus::AwaitingPayment, PermitStatus::Rejected],
    'revoke before issuance' => [PermitStatus::AwaitingPayment, PermitStatus::Revoked],
    'withdraw after approval' => [PermitStatus::AwaitingPayment, PermitStatus::Withdrawn],
    'revoke while payment is checked' => [PermitStatus::PaymentSubmitted, PermitStatus::Revoked],
    'store the computed active status' => [PermitStatus::Issued, PermitStatus::Active],
    'take back an issued permit to payment' => [PermitStatus::Issued, PermitStatus::AwaitingPayment],
    'reopen a rejected application' => [PermitStatus::Rejected, PermitStatus::Submitted],
    'approve a rejected application' => [PermitStatus::Rejected, PermitStatus::AwaitingPayment],
    'renew an expired permit' => [PermitStatus::Expired, PermitStatus::Issued],
    'revoke an expired permit' => [PermitStatus::Expired, PermitStatus::Revoked],
    'reinstate a revoked permit' => [PermitStatus::Revoked, PermitStatus::Issued],
    'reopen a withdrawn application' => [PermitStatus::Withdrawn, PermitStatus::Draft],
    'resubmit a withdrawn application' => [PermitStatus::Withdrawn, PermitStatus::Submitted],
]);

it('allows nothing beyond the listed transitions', function () {
    $machine = new PermitStateMachine;
    $allowed = array_map(
        fn (array $pair): string => "{$pair[0]->value}>{$pair[1]->value}",
        array_values(ALLOWED_TRANSITIONS),
    );

    foreach (PermitStatus::cases() as $from) {
        foreach (PermitStatus::cases() as $to) {
            expect($machine->canTransition($from, $to))
                ->toBe(in_array("{$from->value}>{$to->value}", $allowed, true), "{$from->value} → {$to->value}");
        }
    }
});

it('treats rejected, expired, revoked and withdrawn as final', function (PermitStatus $status, bool $terminal) {
    $machine = new PermitStateMachine;

    expect($machine->isTerminal($status))->toBe($terminal)
        ->and($machine->targets($status) === [])->toBe($terminal);
})->with([
    [PermitStatus::Rejected, true],
    [PermitStatus::Expired, true],
    [PermitStatus::Revoked, true],
    [PermitStatus::Withdrawn, true],
    [PermitStatus::Draft, false],
    [PermitStatus::Submitted, false],
    [PermitStatus::UnderReview, false],
    [PermitStatus::ChangesRequested, false],
    [PermitStatus::AwaitingPayment, false],
    [PermitStatus::PaymentSubmitted, false],
    [PermitStatus::PaymentConfirmed, false],
    [PermitStatus::Issued, false],
]);

it('reports the statuses involved in a refused transition', function () {
    try {
        (new PermitStateMachine)->assertCanTransition(PermitStatus::Rejected, PermitStatus::Issued);
    } catch (InvalidPermitTransition $exception) {
        expect($exception->from)->toBe(PermitStatus::Rejected)
            ->and($exception->to)->toBe(PermitStatus::Issued);

        return;
    }

    $this->fail('The transition was not refused.');
});

it('never stores the computed active status', function () {
    expect(PermitStatus::stored())->not->toContain(PermitStatus::Active)
        ->and(PermitStatus::stored())->toHaveCount(count(PermitStatus::cases()) - 1);
});
