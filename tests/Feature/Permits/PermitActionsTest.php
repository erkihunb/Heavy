<?php

use App\Actions\Permits\ApprovePermit;
use App\Actions\Permits\CreateDraftPermit;
use App\Actions\Permits\RecommendPermit;
use App\Actions\Permits\RejectPermit;
use App\Actions\Permits\RequestChanges;
use App\Actions\Permits\RevokePermit;
use App\Actions\Permits\StartReview;
use App\Actions\Permits\SubmitPermit;
use App\Actions\Permits\UpdateDraftPermit;
use App\Actions\Permits\WithdrawPermit;
use App\Data\RecommendationData;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Recommendation;
use App\Enums\Role;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/*
 * Every Action in App\Actions\Permits: the status it leaves the permit in,
 * the row it appends to the history, and what else it writes. The factories
 * write no history, so every history row seen here was written by an Action.
 */

beforeEach(function () {
    $this->applicant = applicantWithOrganization();
    $this->organization = $this->applicant->organization;
    $this->vehicle = Vehicle::factory()->for($this->organization)->create();
    $this->reviewer = userWithRole(Role::Reviewer);
    $this->approver = userWithRole(Role::Approver);
});

/**
 * The history of a permit as plain values, oldest first.
 *
 * @return list<array{0: string|null, 1: string, 2: int|null, 3: string|null}>
 */
function permitHistory(Permit $permit): array
{
    return PermitStatusTransition::query()
        ->where('permit_id', $permit->id)
        ->orderBy('id')
        ->get()
        ->map(fn (PermitStatusTransition $row): array => [
            $row->from_status?->value,
            $row->to_status->value,
            $row->actor_id,
            $row->note,
        ])
        ->all();
}

/**
 * Asserts that the closure is stopped by the given permit rule.
 */
function expectPermitRule(Closure $action, string $rule): void
{
    try {
        $action();
    } catch (PermitRuleViolation $violation) {
        expect($violation->rule)->toBe($rule);

        return;
    }

    test()->fail("Expected the permit rule [{$rule}] to stop the action.");
}

describe('CreateDraftPermit', function () {
    it('starts a draft for the organization with its vehicles and the first history row', function () {
        $trailer = Vehicle::factory()->for($this->organization)->create();
        $data = permitData($this->vehicle, [$trailer]);

        $permit = app(CreateDraftPermit::class)->handle($this->organization, $this->applicant, $data);
        $permit->refresh();

        expect($permit->status)->toBe(PermitStatus::Draft)
            ->and($permit->organization_id)->toBe($this->organization->id)
            ->and($permit->applicant_id)->toBe($this->applicant->id)
            ->and($permit->permit_type)->toBe(PermitType::Pass)
            ->and($permit->reason)->toBe('የግንባታ ዕቃ ማጓጓዝ')
            ->and($permit->valid_from->toDateString())->toBe($data->validFrom->toDateString())
            ->and($permit->valid_to->toDateString())->toBe($data->validTo->toDateString())
            ->and($permit->permit_number)->toBeNull()
            ->and($permit->submitted_at)->toBeNull()
            ->and($permit->currency)->toBe('ETB')
            ->and(strlen($permit->id))->toBe(26);

        $rows = permitVehicleRows($permit);

        expect($rows)->toHaveCount(2)
            ->and($rows[0]->vehicle_id)->toBe($this->vehicle->id)
            ->and($rows[0]->role)->toBe('primary')
            ->and($rows[1]->vehicle_id)->toBe($trailer->id)
            ->and($rows[1]->role)->toBe('trailer');

        foreach ($rows as $row) {
            expect($row->is_blocking)->toBeFalse()
                ->and($row->period_from)->toBe($data->validFrom->toDateString())
                ->and($row->period_to)->toBe($data->validTo->toDateString());
        }

        expect(permitHistory($permit))->toBe([[null, 'draft', $this->applicant->id, null]]);
    });

    it('refuses a period that breaks the validity rules', function (int $fromOffset, int $toOffset, string $rule) {
        $today = ValidityRules::today();

        expectPermitRule(
            fn () => app(CreateDraftPermit::class)->handle(
                $this->organization,
                $this->applicant,
                permitData($this->vehicle, from: $today->addDays($fromOffset)->toDateString(), to: $today->addDays($toOffset)->toDateString()),
            ),
            $rule,
        );

        expect(Permit::count())->toBe(0);
    })->with([
        'first day in the past' => [-1, 3, 'valid_from_in_past'],
        'last day before the first' => [5, 4, 'valid_to_before_from'],
        // 90 days is the default maximum; day 1 to day 91 is 91 days.
        'longer than the maximum' => [1, 91, 'duration_too_long'],
    ]);

    it('accepts the longest period allowed and one that starts today', function () {
        $today = ValidityRules::today();
        $data = permitData($this->vehicle, from: $today->toDateString(), to: $today->addDays(89)->toDateString());

        $permit = app(CreateDraftPermit::class)->handle($this->organization, $this->applicant, $data);

        expect($permit->durationDays())->toBe(90)->toBe(ValidityRules::maxDurationDays());
    });

    it('refuses vehicles the organization does not own and leaves nothing behind', function () {
        $foreign = Vehicle::factory()->create();

        expectPermitRule(
            fn () => app(CreateDraftPermit::class)->handle($this->organization, $this->applicant, permitData($foreign)),
            'vehicles_not_owned',
        );

        expectPermitRule(
            fn () => app(CreateDraftPermit::class)->handle($this->organization, $this->applicant, permitData($this->vehicle, [$foreign])),
            'vehicles_not_owned',
        );

        expect(Permit::count())->toBe(0)
            ->and(DB::table('permit_vehicles')->count())->toBe(0)
            ->and(PermitStatusTransition::count())->toBe(0);
    });

    it('refuses more trailers than the configured maximum', function () {
        config(['permits.max_trailers' => 1]);
        $trailers = Vehicle::factory()->for($this->organization)->count(2)->create()->all();

        expectPermitRule(
            fn () => app(CreateDraftPermit::class)->handle($this->organization, $this->applicant, permitData($this->vehicle, $trailers)),
            'too_many_trailers',
        );

        expect(Permit::count())->toBe(0);
    });

    it('refuses a vehicle that is listed twice', function () {
        expectPermitRule(
            fn () => app(CreateDraftPermit::class)->handle($this->organization, $this->applicant, permitData($this->vehicle, [$this->vehicle])),
            'vehicles_duplicate',
        );

        expect(Permit::count())->toBe(0);
    });
});

describe('UpdateDraftPermit', function () {
    it('changes the details and the vehicles of a draft without touching status or history', function () {
        $permit = app(CreateDraftPermit::class)->handle($this->organization, $this->applicant, permitData($this->vehicle));
        $other = Vehicle::factory()->for($this->organization)->create();
        $today = ValidityRules::today();

        $data = permitData($other, [$this->vehicle], $today->addDays(20)->toDateString(), $today->addDays(25)->toDateString());

        app(UpdateDraftPermit::class)->handle($permit, $data);
        $permit->refresh();

        expect($permit->status)->toBe(PermitStatus::Draft)
            ->and($permit->valid_from->toDateString())->toBe($today->addDays(20)->toDateString())
            ->and($permit->valid_to->toDateString())->toBe($today->addDays(25)->toDateString());

        $rows = permitVehicleRows($permit);

        expect($rows)->toHaveCount(2)
            ->and($rows[0]->vehicle_id)->toBe($other->id)
            ->and($rows[0]->role)->toBe('primary')
            ->and($rows[0]->period_from)->toBe($today->addDays(20)->toDateString())
            ->and($rows[0]->period_to)->toBe($today->addDays(25)->toDateString())
            ->and($rows[0]->is_blocking)->toBeFalse()
            ->and($rows[1]->vehicle_id)->toBe($this->vehicle->id)
            ->and($rows[1]->role)->toBe('trailer')
            ->and(permitHistory($permit))->toHaveCount(1);
    });

    it('changes an application that was sent back for changes', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->changesRequested()->create();
        $other = Vehicle::factory()->for($this->organization)->create();

        app(UpdateDraftPermit::class)->handle($permit, permitData($other));

        expect($permit->refresh()->status)->toBe(PermitStatus::ChangesRequested)
            ->and(permitVehicleRows($permit)->pluck('vehicle_id')->all())->toBe([$other->id])
            ->and(permitHistory($permit))->toBe([]);
    });

    it('refuses to change an application in any other stage', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);
        $other = Vehicle::factory()->for($this->organization)->create();
        $reason = $permit->reason;

        expectPermitRule(fn () => app(UpdateDraftPermit::class)->handle($permit, permitData($other)), 'not_editable');

        expect($permit->refresh()->reason)->toBe($reason)
            ->and(permitVehicleRows($permit)->pluck('vehicle_id')->all())->toBe([$this->vehicle->id]);
    })->with([
        PermitStatus::Submitted,
        PermitStatus::UnderReview,
        PermitStatus::Rejected,
        PermitStatus::Withdrawn,
    ]);

    it('refuses an invalid period and vehicles of another organization', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create();
        $today = ValidityRules::today();

        expectPermitRule(
            fn () => app(UpdateDraftPermit::class)->handle($permit, permitData($this->vehicle, from: $today->subDays(2)->toDateString())),
            'valid_from_in_past',
        );

        expectPermitRule(
            fn () => app(UpdateDraftPermit::class)->handle($permit, permitData(Vehicle::factory()->create())),
            'vehicles_not_owned',
        );

        expect(permitVehicleRows($permit)->pluck('vehicle_id')->all())->toBe([$this->vehicle->id]);
    });
});

describe('SubmitPermit', function () {
    it('hands a draft to the Authority', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create(['applicant_id' => $this->applicant->id]);

        $this->freezeSecond();

        app(SubmitPermit::class)->handle($permit, $this->applicant);
        $permit->refresh();

        expect($permit->status)->toBe(PermitStatus::Submitted)
            ->and($permit->submitted_at?->equalTo(now()))->toBeTrue()
            ->and($permit->permit_number)->toBeNull()
            ->and(permitHistory($permit))->toBe([['draft', 'submitted', $this->applicant->id, null]])
            ->and(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([false]);
    });

    it('resubmits an application that was sent back, clearing the old recommendation', function () {
        $permit = Permit::factory()
            ->forVehicles($this->vehicle)
            ->recommended(Recommendation::Reject, $this->reviewer)
            ->create(['status' => PermitStatus::ChangesRequested, 'submitted_at' => now()->subDays(3)]);

        $this->freezeSecond();

        app(SubmitPermit::class)->handle($permit, $this->applicant);
        $permit->refresh();

        expect($permit->status)->toBe(PermitStatus::Submitted)
            ->and($permit->submitted_at?->equalTo(now()))->toBeTrue()
            ->and($permit->recommendation)->toBeNull()
            ->and($permit->recommended_by)->toBeNull()
            ->and($permit->recommended_at)->toBeNull()
            ->and($permit->recommendation_note)->toBeNull()
            ->and(permitHistory($permit))->toBe([['changes_requested', 'submitted', $this->applicant->id, null]]);
    });

    it('refuses a draft whose first day has passed in the meantime', function () {
        $today = ValidityRules::today();
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()
            ->validBetween($today->subDay(), $today->addDays(5))
            ->create();

        expectPermitRule(fn () => app(SubmitPermit::class)->handle($permit, $this->applicant), 'valid_from_in_past');

        expect($permit->refresh()->status)->toBe(PermitStatus::Draft)
            ->and($permit->submitted_at)->toBeNull()
            ->and(permitHistory($permit))->toBe([]);
    });

    it('refuses a draft without a primary vehicle', function () {
        $permit = Permit::factory()->withoutVehicles()->draft()->create(['organization_id' => $this->organization->id]);

        expectPermitRule(fn () => app(SubmitPermit::class)->handle($permit, $this->applicant), 'primary_vehicle_required');

        expect($permit->refresh()->status)->toBe(PermitStatus::Draft);
    });

    it('refuses a draft with a vehicle that no longer belongs to the organization', function () {
        $permit = Permit::factory()->forVehicles(Vehicle::factory()->create())->draft()
            ->create(['organization_id' => $this->organization->id]);

        expectPermitRule(fn () => app(SubmitPermit::class)->handle($permit, $this->applicant), 'vehicles_not_owned');

        expect($permit->refresh()->status)->toBe(PermitStatus::Draft);
    });

    it('cannot submit an application that is not a draft or sent back', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        expect(fn () => app(SubmitPermit::class)->handle($permit, $this->applicant))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->status)->toBe($status)
            ->and(permitHistory($permit))->toBe([]);
    })->with([
        PermitStatus::Submitted,
        PermitStatus::UnderReview,
        PermitStatus::Rejected,
        PermitStatus::AwaitingPayment,
        PermitStatus::Withdrawn,
    ]);
});

describe('WithdrawPermit', function () {
    it('takes back a draft or an application nobody has started reviewing', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        app(WithdrawPermit::class)->handle($permit, $this->applicant);

        expect($permit->refresh()->status)->toBe(PermitStatus::Withdrawn)
            ->and(permitHistory($permit))->toBe([[$status->value, 'withdrawn', $this->applicant->id, null]])
            ->and(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([false]);
    })->with([PermitStatus::Draft, PermitStatus::Submitted]);

    it('cannot withdraw once the review has started', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        expect(fn () => app(WithdrawPermit::class)->handle($permit, $this->applicant))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->status)->toBe($status)
            ->and(permitHistory($permit))->toBe([]);
    })->with([
        PermitStatus::UnderReview,
        PermitStatus::ChangesRequested,
        PermitStatus::AwaitingPayment,
        PermitStatus::Issued,
        PermitStatus::Withdrawn,
    ]);
});

describe('StartReview', function () {
    it('takes a submitted application out of the queue', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();
        $submittedAt = $permit->submitted_at;

        app(StartReview::class)->handle($permit, $this->reviewer);

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->submitted_at?->equalTo($submittedAt))->toBeTrue()
            ->and(permitHistory($permit))->toBe([['submitted', 'under_review', $this->reviewer->id, null]]);
    });

    it('cannot start reviewing anything that is not waiting in the queue', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        expect(fn () => app(StartReview::class)->handle($permit, $this->reviewer))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->status)->toBe($status)
            ->and(permitHistory($permit))->toBe([]);
    })->with([
        PermitStatus::Draft,
        PermitStatus::UnderReview,
        PermitStatus::ChangesRequested,
        PermitStatus::Rejected,
        PermitStatus::Withdrawn,
    ]);
});

describe('RecommendPermit', function () {
    it('records the advice of the Reviewer without changing the status or the history', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        $this->freezeSecond();

        app(RecommendPermit::class)->handle($permit, $this->reviewer, new RecommendationData(Recommendation::Approve, '  ሰነዶቹ ተሟልተዋል  '));
        $permit->refresh();

        expect($permit->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->recommendation)->toBe(Recommendation::Approve)
            ->and($permit->recommended_by)->toBe($this->reviewer->id)
            ->and($permit->recommended_at?->equalTo(now()))->toBeTrue()
            ->and($permit->recommendation_note)->toBe('ሰነዶቹ ተሟልተዋል')
            ->and(permitHistory($permit))->toBe([]);
    });

    it('lets a later recommendation replace the earlier one', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended(Recommendation::Reject)->create();
        $second = userWithRole(Role::Reviewer);

        app(RecommendPermit::class)->handle($permit, $second, new RecommendationData(Recommendation::Approve, null));
        $permit->refresh();

        expect($permit->recommendation)->toBe(Recommendation::Approve)
            ->and($permit->recommended_by)->toBe($second->id)
            ->and($permit->recommendation_note)->toBeNull();
    });

    it('needs a note when advising rejection', function (?string $note) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        expectPermitRule(
            fn () => app(RecommendPermit::class)->handle($permit, $this->reviewer, new RecommendationData(Recommendation::Reject, $note)),
            'note_required',
        );

        expect($permit->refresh()->recommendation)->toBeNull();
    })->with([null, '', '   ']);

    it('records a recommendation to reject with its note', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        app(RecommendPermit::class)->handle($permit, $this->reviewer, new RecommendationData(Recommendation::Reject, 'መንገዱ አይፈቅድም'));

        expect($permit->refresh()->recommendation)->toBe(Recommendation::Reject)
            ->and($permit->recommendation_note)->toBe('መንገዱ አይፈቅድም');
    });

    it('only records a recommendation while the application is under review', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        expectPermitRule(
            fn () => app(RecommendPermit::class)->handle($permit, $this->reviewer, new RecommendationData(Recommendation::Approve, null)),
            'not_under_review',
        );

        expect($permit->refresh()->recommendation)->toBeNull()
            ->and($permit->recommended_by)->toBeNull();
    })->with([
        PermitStatus::Draft,
        PermitStatus::Submitted,
        PermitStatus::ChangesRequested,
        PermitStatus::AwaitingPayment,
        PermitStatus::Rejected,
    ]);
});

describe('RequestChanges', function () {
    it('sends the application back to the applicant with a note', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        app(RequestChanges::class)->handle($permit, $this->reviewer, '  የተሽከርካሪውን ሊብሬ ያያይዙ ');

        expect($permit->refresh()->status)->toBe(PermitStatus::ChangesRequested)
            ->and(permitHistory($permit))->toBe([['under_review', 'changes_requested', $this->reviewer->id, 'የተሽከርካሪውን ሊብሬ ያያይዙ']]);
    });

    it('needs a note', function (string $note) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        expectPermitRule(fn () => app(RequestChanges::class)->handle($permit, $this->reviewer, $note), 'note_required');

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and(permitHistory($permit))->toBe([]);
    })->with(['', '   ']);

    it('cannot ask for changes outside the review', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        expect(fn () => app(RequestChanges::class)->handle($permit, $this->reviewer, 'ማስታወሻ'))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->status)->toBe($status);
    })->with([
        PermitStatus::Draft,
        PermitStatus::Submitted,
        PermitStatus::ChangesRequested,
        PermitStatus::AwaitingPayment,
    ]);
});

describe('ApprovePermit', function () {
    it('approves a recommended application: number, approver, reserved vehicles, history', function () {
        $trailer = Vehicle::factory()->for($this->organization)->create();
        $permit = Permit::factory()->forVehicles($this->vehicle, $trailer)->recommended(reviewer: $this->reviewer)->create();

        $this->freezeSecond();

        app(ApprovePermit::class)->handle($permit, $this->approver, ' ጸድቋል ');
        $permit->refresh();

        expect($permit->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($permit->permit_number)->toMatch('/^TMA-HV-\d{4}-000001$/')
            ->and($permit->approved_by)->toBe($this->approver->id)
            ->and($permit->approved_at?->equalTo(now()))->toBeTrue()
            ->and($permit->issued_at)->toBeNull()
            ->and($permit->fee_cents)->toBeNull()
            // The recommendation stays on record.
            ->and($permit->recommendation)->toBe(Recommendation::Approve)
            ->and($permit->recommended_by)->toBe($this->reviewer->id)
            ->and(permitHistory($permit))->toBe([['under_review', 'awaiting_payment', $this->approver->id, 'ጸድቋል']]);

        $rows = permitVehicleRows($permit);

        expect($rows)->toHaveCount(2);

        foreach ($rows as $row) {
            expect($row->is_blocking)->toBeTrue()
                ->and($row->period_from)->toBe($permit->valid_from->toDateString())
                ->and($row->period_to)->toBe($permit->valid_to->toDateString());
        }
    });

    it('records no note when none is given', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()->create();

        app(ApprovePermit::class)->handle($permit, $this->approver);

        expect(permitHistory($permit))->toBe([['under_review', 'awaiting_payment', $this->approver->id, null]]);
    });

    it('needs a recommendation first when approval is two-step', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        expectPermitRule(fn () => app(ApprovePermit::class)->handle($permit, $this->approver), 'recommendation_required');

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->permit_number)->toBeNull()
            ->and($permit->approved_by)->toBeNull()
            ->and(permitHistory($permit))->toBe([])
            ->and(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([false])
            // No number was used up.
            ->and(DB::table('permit_number_sequences')->count())->toBe(0);
    });

    it('does not let the person who recommended also decide', function () {
        $both = userWithRole(Role::Reviewer);
        $both->assignRole(Role::Approver->value);
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended(reviewer: $both)->create();

        expectPermitRule(fn () => app(ApprovePermit::class)->handle($permit, $both), 'approver_is_recommender');

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->permit_number)->toBeNull();

        app(ApprovePermit::class)->handle($permit, $this->approver);

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    });

    it('leaves the decision to the Approver, whatever was recommended', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended(Recommendation::Reject)->create();

        app(ApprovePermit::class)->handle($permit, $this->approver);

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    });

    it('approves without a recommendation when two-step approval is switched off', function () {
        config(['permits.two_step_approval' => false]);
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        app(ApprovePermit::class)->handle($permit, $this->approver);

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($permit->permit_number)->not->toBeNull();
    });

    it('cannot approve an application that is not under review', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()->create(['status' => $status]);

        expect(fn () => app(ApprovePermit::class)->handle($permit, $this->approver))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->status)->toBe($status)
            ->and($permit->permit_number)->toBeNull()
            ->and(permitHistory($permit))->toBe([])
            ->and(DB::table('permit_number_sequences')->count())->toBe(0);
    })->with([
        PermitStatus::Draft,
        PermitStatus::Submitted,
        PermitStatus::ChangesRequested,
        PermitStatus::Rejected,
        PermitStatus::Withdrawn,
    ]);

    it('cannot approve twice', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()->create();

        app(ApprovePermit::class)->handle($permit, $this->approver);
        $number = $permit->refresh()->permit_number;

        expect(fn () => app(ApprovePermit::class)->handle($permit, $this->approver))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->permit_number)->toBe($number)
            ->and(permitHistory($permit))->toHaveCount(1);
    });
});

describe('RejectPermit', function () {
    it('refuses a recommended application for good, with the reason', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended(Recommendation::Reject, $this->reviewer)->create();

        app(RejectPermit::class)->handle($permit, $this->approver, ' መንገዱ ክብደቱን አይችልም ');
        $permit->refresh();

        expect($permit->status)->toBe(PermitStatus::Rejected)
            ->and($permit->permit_number)->toBeNull()
            ->and($permit->approved_by)->toBeNull()
            ->and(permitHistory($permit))->toBe([['under_review', 'rejected', $this->approver->id, 'መንገዱ ክብደቱን አይችልም']])
            ->and(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([false]);
    });

    it('needs a note', function (string $note) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()->create();

        expectPermitRule(fn () => app(RejectPermit::class)->handle($permit, $this->approver, $note), 'note_required');

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    })->with(['', '  ']);

    it('follows the two-step rules', function () {
        $unrecommended = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        expectPermitRule(fn () => app(RejectPermit::class)->handle($unrecommended, $this->approver, 'ምክንያት'), 'recommendation_required');

        $both = userWithRole(Role::Approver);
        $own = Permit::factory()->forVehicles($this->vehicle)->recommended(reviewer: $both)->create();

        expectPermitRule(fn () => app(RejectPermit::class)->handle($own, $both, 'ምክንያት'), 'approver_is_recommender');

        expect($unrecommended->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($own->refresh()->status)->toBe(PermitStatus::UnderReview);

        config(['permits.two_step_approval' => false]);

        app(RejectPermit::class)->handle($unrecommended, $this->approver, 'ምክንያት');

        expect($unrecommended->refresh()->status)->toBe(PermitStatus::Rejected);
    });

    it('cannot reject an application that is not under review', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()->create(['status' => $status]);

        expect(fn () => app(RejectPermit::class)->handle($permit, $this->approver, 'ምክንያት'))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->status)->toBe($status)
            ->and(permitHistory($permit))->toBe([]);
    })->with([
        PermitStatus::Submitted,
        PermitStatus::ChangesRequested,
        PermitStatus::AwaitingPayment,
        PermitStatus::Rejected,
    ]);
});

describe('RevokePermit', function () {
    it('cancels an issued permit, records who and why, and frees its vehicles', function (string $state) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->{$state}()->create();

        expect(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([true]);

        $this->freezeSecond();

        app(RevokePermit::class)->handle($permit, $this->approver, '  የተሳሳተ መረጃ ቀርቧል ');
        $permit->refresh();

        expect($permit->status)->toBe(PermitStatus::Revoked)
            ->and($permit->revoked_by)->toBe($this->approver->id)
            ->and($permit->revoked_at?->equalTo(now()))->toBeTrue()
            ->and($permit->revocation_reason)->toBe('የተሳሳተ መረጃ ቀርቧል')
            // The number stays with the permit.
            ->and($permit->permit_number)->not->toBeNull()
            ->and(permitHistory($permit))->toBe([['issued', 'revoked', $this->approver->id, 'የተሳሳተ መረጃ ቀርቧል']])
            ->and(permitVehicleRows($permit)->pluck('is_blocking')->all())->toBe([false]);
    })->with(['issued', 'active']);

    it('needs a reason', function (string $reason) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->issued()->create();

        expectPermitRule(fn () => app(RevokePermit::class)->handle($permit, $this->approver, $reason), 'revocation_reason_required');

        expect($permit->refresh()->status)->toBe(PermitStatus::Issued)
            ->and($permit->revoked_at)->toBeNull();
    })->with(['', '   ']);

    it('cannot revoke a permit that has not been issued', function (string $state) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->{$state}()->create();
        $status = $permit->status;

        expect(fn () => app(RevokePermit::class)->handle($permit, $this->approver, 'ምክንያት'))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->status)->toBe($status)
            ->and($permit->revoked_by)->toBeNull()
            ->and($permit->revocation_reason)->toBeNull()
            ->and(permitHistory($permit))->toBe([]);
    })->with(['underReview', 'awaitingPayment', 'paymentSubmitted', 'expired']);

    it('cannot revoke twice', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->revoked()->create();
        $reason = $permit->revocation_reason;

        expect(fn () => app(RevokePermit::class)->handle($permit, $this->approver, 'ሌላ ምክንያት'))
            ->toThrow(InvalidPermitTransition::class);

        expect($permit->refresh()->revocation_reason)->toBe($reason);
    });
});

it('describes the failed move in an invalid-transition exception', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create();

    try {
        app(StartReview::class)->handle($permit, $this->reviewer);
    } catch (InvalidPermitTransition $exception) {
        expect($exception->from)->toBe(PermitStatus::Draft)
            ->and($exception->to)->toBe(PermitStatus::UnderReview)
            ->and($exception->userMessage())->toBe(__('permits.errors.invalid_transition', [
                'status' => __('permits.status.draft'),
            ]));

        return;
    }

    $this->fail('The transition was allowed.');
});

it('decides on the stored status, not on a stale copy of the permit', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();
    $stale = Permit::findOrFail($permit->id);

    app(WithdrawPermit::class)->handle($permit, $this->applicant);

    expect(fn () => app(StartReview::class)->handle($stale, $this->reviewer))
        ->toThrow(InvalidPermitTransition::class);

    expect($permit->refresh()->status)->toBe(PermitStatus::Withdrawn);
});

it('writes the whole story of an application to its history, in order', function () {
    $second = userWithRole(Role::Reviewer);

    $permit = app(CreateDraftPermit::class)->handle($this->organization, $this->applicant, permitData($this->vehicle));
    app(SubmitPermit::class)->handle($permit, $this->applicant);
    app(StartReview::class)->handle($permit, $this->reviewer);
    app(RequestChanges::class)->handle($permit, $this->reviewer, 'ቀኑን ያስተካክሉ');
    app(UpdateDraftPermit::class)->handle($permit, permitData($this->vehicle));
    app(SubmitPermit::class)->handle($permit, $this->applicant);
    app(StartReview::class)->handle($permit, $second);
    app(RecommendPermit::class)->handle($permit, $second, new RecommendationData(Recommendation::Approve, null));
    app(ApprovePermit::class)->handle($permit, $this->approver, 'ጸድቋል');

    expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
        ->and(permitHistory($permit))->toBe([
            [null, 'draft', $this->applicant->id, null],
            ['draft', 'submitted', $this->applicant->id, null],
            ['submitted', 'under_review', $this->reviewer->id, null],
            ['under_review', 'changes_requested', $this->reviewer->id, 'ቀኑን ያስተካክሉ'],
            ['changes_requested', 'submitted', $this->applicant->id, null],
            ['submitted', 'under_review', $second->id, null],
            ['under_review', 'awaiting_payment', $this->approver->id, 'ጸድቋል'],
        ])
        ->and($permit->organization_id)->toBe($this->organization->id)
        ->and($permit->applicant_id)->toBe($this->applicant->id);
});
