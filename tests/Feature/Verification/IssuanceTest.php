<?php

use App\Actions\Permits\ReissuePermit;
use App\Actions\Permits\VerifyPayment;
use App\Domain\Permits\PermitVerifier;
use App\Domain\Permits\VerificationTokenService;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Enums\ScanResult;
use App\Exceptions\PermitRuleViolation;
use App\Jobs\GeneratePermitDocument;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/*
 * Issuing a permit gives it a verification token (spec §7) and queues its
 * document (spec §8); reissuing rotates the token; revoking takes effect for
 * verification at once (spec §4.8).
 */

beforeEach(function () {
    Storage::fake('documents');
    Bus::fake([GeneratePermitDocument::class]);

    $this->cashier = userWithRole(Role::Cashier);
    $this->permit = Permit::factory()->active()->create(['status' => PermitStatus::PaymentSubmitted]);
    $this->payment = Payment::factory()->forPermit($this->permit)->create();
});

/**
 * The token the queued document job was given for the permit.
 */
function queuedToken(Permit $permit): string
{
    $token = null;

    Bus::assertDispatched(GeneratePermitDocument::class, function (GeneratePermitDocument $job) use ($permit, &$token): bool {
        if ($job->permitId !== $permit->id) {
            return false;
        }

        $token = $job->token;

        return true;
    });

    return (string) $token;
}

describe('issuing', function () {
    it('gives the permit a token whose hash only is stored, and queues its document', function () {
        app(VerifyPayment::class)->handle($this->payment, $this->cashier);

        $this->permit->refresh();
        $token = queuedToken($this->permit);

        expect($token)->toMatch(VerificationTokenService::PATTERN)
            ->and($this->permit->verification_token_hash)->toBe(hash('sha256', $token))
            ->and($this->permit->verification_token_hash)->not->toContain($token)
            ->and($this->permit->document_path)->toBeNull()
            ->and(app(PermitVerifier::class)->byToken($token)?->id)->toBe($this->permit->id);
    });

    it('encrypts the job, because it carries the token', function () {
        expect(new GeneratePermitDocument('x', 'y'))->toBeInstanceOf(ShouldBeEncrypted::class);
    });

    it('queues nothing when the payment is refused', function () {
        $this->permit->forceFill(['approved_by' => $this->cashier->id])->save();

        expect(fn () => app(VerifyPayment::class)->handle($this->payment, $this->cashier))->toThrow(PermitRuleViolation::class);

        Bus::assertNotDispatched(GeneratePermitDocument::class);
        expect($this->permit->refresh()->verification_token_hash)->toBeNull();
    });

    it('gives every permit a different token', function () {
        $other = Permit::factory()->paymentSubmitted()->create();
        $otherPayment = Payment::factory()->forPermit($other)->create();

        app(VerifyPayment::class)->handle($this->payment, $this->cashier);
        app(VerifyPayment::class)->handle($otherPayment, $this->cashier);

        expect(queuedToken($this->permit))->not->toBe(queuedToken($other));
    });
});

describe('reissuing', function () {
    beforeEach(function () {
        app(VerifyPayment::class)->handle($this->payment, $this->cashier);
        $this->oldToken = queuedToken($this->permit->refresh());
        $this->approver = userWithRole(Role::Approver);
    });

    it('rotates the token: the old QR code stops working, the new one works', function () {
        Storage::disk('documents')->put('permits/old.pdf', '%PDF');
        $this->permit->forceFill(['document_path' => 'permits/old.pdf'])->save();
        Bus::fake([GeneratePermitDocument::class]);

        app(ReissuePermit::class)->handle($this->permit, $this->approver, 'Driver lost the paper.');

        $newToken = queuedToken($this->permit);
        $verifier = app(PermitVerifier::class);

        expect($newToken)->not->toBe($this->oldToken)
            ->and($verifier->byToken($this->oldToken))->toBeNull()
            ->and($verifier->result($verifier->byToken($this->oldToken)))->toBe(ScanResult::NotFound)
            ->and($verifier->byToken($newToken)?->id)->toBe($this->permit->id)
            ->and($this->permit->refresh()->status)->toBe(PermitStatus::Issued)
            ->and($this->permit->document_path)->toBeNull();

        Storage::disk('documents')->assertMissing('permits/old.pdf');

        $step = PermitStatusTransition::query()->where('permit_id', $this->permit->id)->latest('id')->first();

        expect($step->from_status)->toBe(PermitStatus::Issued)
            ->and($step->to_status)->toBe(PermitStatus::Issued)
            ->and($step->actor_id)->toBe($this->approver->id)
            ->and($step->note)->toBe('Driver lost the paper.');
    });

    it('requires a reason', function () {
        expect(fn () => app(ReissuePermit::class)->handle($this->permit, $this->approver, '  '))
            ->toThrow(fn (PermitRuleViolation $e) => expect($e->rule)->toBe('reissue_reason_required'));
    });

    it('refuses a permit that is no longer live', function (string $state) {
        $permit = Permit::factory()->{$state}()->create(['verification_token_hash' => str_repeat('a', 64)]);

        expect(fn () => app(ReissuePermit::class)->handle($permit, $this->approver, 'Lost.'))
            ->toThrow(fn (PermitRuleViolation $e) => expect($e->rule)->toBe('not_reissuable'));

        expect($permit->refresh()->verification_token_hash)->toBe(str_repeat('a', 64));
    })->with(['revoked', 'expired', 'awaitingPayment']);

    it('is done over HTTP by an Approver, with a reason', function () {
        $approver = staffUser(Role::Approver);

        $this->actingAs($approver)
            ->post(route('staff.permits.reissue', $this->permit), ['note' => ''])
            ->assertSessionHasErrors(['note' => __('permits.errors.reissue_reason_required')]);

        $this->actingAs($approver)
            ->post(route('staff.permits.reissue', $this->permit), ['note' => 'Damaged paper.'])
            ->assertRedirect(route('staff.permits.show', $this->permit))
            ->assertSessionHas('success', __('permits.messages.reissued'));

        expect(app(PermitVerifier::class)->byToken($this->oldToken))->toBeNull();
    });

    it('is not for Reviewers, cashiers or officers', function (Role $role) {
        $this->actingAs(staffUser($role))
            ->post(route('staff.permits.reissue', $this->permit), ['note' => 'x'])
            ->assertForbidden();

        expect(app(PermitVerifier::class)->byToken($this->oldToken)?->id)->toBe($this->permit->id);
    })->with([Role::Reviewer, Role::Cashier, Role::TrafficOfficer]);
});

describe('revoking', function () {
    beforeEach(function () {
        app(VerifyPayment::class)->handle($this->payment, $this->cashier);
        $this->token = queuedToken($this->permit->refresh());
    });

    it('takes effect for verification at once', function () {
        $verifier = app(PermitVerifier::class);
        expect($verifier->result($verifier->byToken($this->token)))->toBe(ScanResult::Valid);

        $this->actingAs(staffUser(Role::Approver))
            ->post(route('staff.permits.revoke', $this->permit), ['note' => 'Vehicle used outside the permitted route.'])
            ->assertRedirect(route('staff.permits.show', $this->permit))
            ->assertSessionHas('success', __('permits.messages.revoked'));

        expect($verifier->result($verifier->byToken($this->token)))->toBe(ScanResult::Revoked)
            ->and($this->permit->refresh()->revocation_reason)->toBe('Vehicle used outside the permitted route.');

        $this->get(route('verification.token', $this->token))->assertSee(__('verification.results.revoked.title'));
    });

    it('requires a reason', function () {
        $this->actingAs(staffUser(Role::Approver))
            ->post(route('staff.permits.revoke', $this->permit), ['note' => ''])
            ->assertSessionHasErrors(['note' => __('permits.errors.revocation_reason_required')]);

        expect($this->permit->refresh()->status)->toBe(PermitStatus::Issued);
    });

    it('is not for Reviewers, cashiers or officers', function (Role $role) {
        $this->actingAs(staffUser($role))
            ->post(route('staff.permits.revoke', $this->permit), ['note' => 'x'])
            ->assertForbidden();

        expect($this->permit->refresh()->status)->toBe(PermitStatus::Issued);
    })->with([Role::Reviewer, Role::Cashier, Role::TrafficOfficer]);
});
