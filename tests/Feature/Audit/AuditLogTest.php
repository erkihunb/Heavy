<?php

use App\Actions\Fees\SaveFeeSchedule;
use App\Actions\Permits\ApprovePermit;
use App\Actions\Permits\ExpirePermits;
use App\Actions\Permits\ReissuePermit;
use App\Actions\Permits\RejectPayment;
use App\Actions\Permits\VerifyPayment;
use App\Data\FeeScheduleData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Role;
use App\Exceptions\InvalidPermitTransition;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/*
 * The audit log (spec §15): who, what, when, before and after — for every
 * state transition, payment decision, reissue and fee schedule change —
 * and it can never be rewritten.
 */

beforeEach(function () {
    Notification::fake();
    Queue::fake();
    Storage::fake('documents');
});

function auditEntries(AuditEvent $event): Collection
{
    return Activity::query()->where('log_name', AuditLog::LOG_NAME)->where('event', $event->value)->orderBy('id')->get();
}

it('records every permit transition with who, from and to', function () {
    withFeeSchedule();
    $approver = userWithRole(Role::Approver);
    $permit = Permit::factory()->recommended()->create();

    app(ApprovePermit::class)->handle($permit, $approver);

    $entry = auditEntries(AuditEvent::PermitTransition)->last();

    expect($entry->subject_type)->toBe($permit->getMorphClass())
        ->and($entry->subject_id)->toBe($permit->id)
        ->and((int) $entry->causer_id)->toBe($approver->id)
        ->and($entry->properties['old'])->toBe(['status' => 'under_review'])
        ->and($entry->properties['attributes'])->toBe(['status' => 'awaiting_payment'])
        ->and($entry->properties['context']['permit_number'])->toBe($permit->refresh()->permit_number);
});

it('records changes the system makes with no person as the actor', function () {
    $today = ValidityRules::today();
    $permit = Permit::factory()->issued()->validBetween($today->subDays(10), $today->subDays(2))->create();

    app(ExpirePermits::class)->handle();

    $entry = auditEntries(AuditEvent::PermitTransition)->firstWhere('subject_id', $permit->id);

    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBeNull()
        ->and($entry->properties['attributes'])->toBe(['status' => 'expired']);
});

it('records payment verification and rejection with the receipt', function () {
    $cashier = userWithRole(Role::Cashier);

    $verified = Payment::factory()->create(['receipt_number' => 'FS-AUDIT-1']);
    app(VerifyPayment::class)->handle($verified, $cashier);

    $rejected = Payment::factory()->create(['receipt_number' => 'FS-AUDIT-2']);
    app(RejectPayment::class)->handle($rejected, $cashier, 'Unreadable.');

    $verification = auditEntries(AuditEvent::PaymentVerified)->sole();
    $rejection = auditEntries(AuditEvent::PaymentRejected)->sole();

    expect($verification->subject_id)->toBe($verified->id)
        ->and((int) $verification->causer_id)->toBe($cashier->id)
        ->and($verification->properties['attributes'])->toBe(['status' => 'verified'])
        ->and($verification->properties['context']['receipt_number'])->toBe('FS-AUDIT-1')
        ->and($rejection->subject_id)->toBe($rejected->id)
        ->and($rejection->properties['old'])->toBe(['status' => 'submitted'])
        ->and($rejection->properties['attributes'])->toBe(['status' => 'rejected']);

    // The permit transitions are recorded as well.
    expect(auditEntries(AuditEvent::PermitTransition)->pluck('properties.attributes.status')->all())
        ->toContain('issued', 'awaiting_payment');
});

it('records a reissue as its own event', function () {
    $approver = userWithRole(Role::Approver);
    $permit = Permit::factory()->active()->create();

    app(ReissuePermit::class)->handle($permit, $approver, 'Driver lost the paper.');

    $entry = auditEntries(AuditEvent::PermitReissued)->sole();

    expect($entry->subject_id)->toBe($permit->id)
        ->and((int) $entry->causer_id)->toBe($approver->id);
});

it('records fee schedule changes with the values before and after', function () {
    $admin = userWithRole(Role::Admin);
    $data = fn (int $cents): FeeScheduleData => new FeeScheduleData(
        permitType: PermitType::FullDay,
        vehicleTypeId: null,
        minDays: 1,
        maxDays: 30,
        amountCents: $cents,
        effectiveFrom: CarbonImmutable::parse('2026-01-01', 'UTC'),
        effectiveTo: null,
    );

    $line = app(SaveFeeSchedule::class)->handle($data(100_000), actor: $admin);
    app(SaveFeeSchedule::class)->handle($data(150_000), $line, $admin);

    [$created, $changed] = auditEntries(AuditEvent::FeeScheduleSaved)->all();

    expect($created->properties->has('old'))->toBeFalse()
        ->and($created->properties['attributes']['amount_cents'])->toBe(100_000)
        ->and($changed->properties['old']['amount_cents'])->toBe(100_000)
        ->and($changed->properties['attributes']['amount_cents'])->toBe(150_000)
        ->and((int) $changed->causer_id)->toBe($admin->id);
});

it('keeps only a hash of the IP address', function () {
    $actor = userWithRole(Role::Admin);
    $this->actingAs($actor);

    app(AuditLog::class)->record(AuditEvent::SettingsUpdated, actor: $actor, new: ['x' => 1]);

    $entry = auditEntries(AuditEvent::SettingsUpdated)->sole();

    expect($entry->properties['ip_hash'])->toMatch('/^[0-9a-f]{64}$/')
        ->and(json_encode($entry->properties))->not->toContain('127.0.0.1');
});

it('cannot be changed or deleted, not even by the application', function () {
    app(AuditLog::class)->record(AuditEvent::SettingsUpdated, new: ['x' => 1]);
    $entry = auditEntries(AuditEvent::SettingsUpdated)->sole();

    expect(fn () => DB::table('activity_log')->where('id', $entry->id)->update(['description' => 'changed']))
        ->toThrow(QueryException::class, 'append-only');

    expect(fn () => DB::table('activity_log')->where('id', $entry->id)->delete())
        ->toThrow(QueryException::class, 'append-only');

    expect(Activity::query()->find($entry->id)->description)->toBe('settings.updated');
});

it('writes nothing about a change that was refused', function () {
    $permit = Permit::factory()->draft()->create();
    $before = Activity::query()->count();

    expect(fn () => app(ApprovePermit::class)->handle($permit, userWithRole(Role::Approver)))
        ->toThrow(InvalidPermitTransition::class);

    expect(Activity::query()->count())->toBe($before)
        ->and($permit->refresh()->status)->toBe(PermitStatus::Draft);
});

it('names the person as causer, never someone else who is logged in', function () {
    $loggedIn = userWithRole(Role::Admin);
    $this->actingAs($loggedIn);

    app(AuditLog::class)->record(AuditEvent::SettingsUpdated, actor: null, new: ['x' => 1]);

    expect(auditEntries(AuditEvent::SettingsUpdated)->sole()->causer_id)->toBeNull();
    expect(User::query()->count())->toBeGreaterThan(0);
});
