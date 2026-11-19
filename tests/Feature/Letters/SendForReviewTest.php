<?php

use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\PermitReviewRequest;
use App\Models\Role as RoleModel;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The person who decides may send a letter's vehicles to chosen Reviewers for
 * their opinion. Reviewers see, and give an opinion on, only what was sent to
 * them; the decision never waits for it. Who may do what is decided by
 * permissions alone.
 */

beforeEach(function () {
    Notification::fake();
    withFeeSchedule();
    $this->registrar = staffUser(Role::Registrar);
    $this->type = VehicleType::factory()->create();
    $this->otherType = VehicleType::factory()->create();

    // Reviewers here give opinions only: the deciding permission is the Approver's.
    RoleModel::findByName(Role::Reviewer->value)->revokePermissionTo(Permission::ApprovePermits->value);

    $this->letter = registeredLetter();
    $this->permits = $this->letter->permits()->orderBy('id')->get();
    $this->approver = staffUser(Role::Approver);
    $this->reviewer = staffUser(Role::Reviewer);
    $this->otherReviewer = staffUser(Role::Reviewer);
});

/**
 * @param  list<string>  $permits
 * @param  list<int>  $reviewers
 */
function sendForReview(array $permits, array $reviewers): TestResponse
{
    return test()->actingAs(test()->approver)
        ->post(route('letters.send-for-review', test()->letter), [
            'permits' => $permits,
            'reviewers' => $reviewers,
        ]);
}

it('offers the person who decides the reviewers to ask', function () {
    $this->actingAs($this->approver)
        ->get(route('letters.show', $this->letter))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.sendForReview', true)
            ->where('can.decide', true)
            ->has('reviewers', 2)
            ->where('review.opinions', [])
            ->where('review.sent', []));
});

it('sends the chosen vehicles to the chosen reviewers only', function () {
    $chosen = $this->permits->take(2)->pluck('id')->all();

    sendForReview($chosen, [$this->reviewer->id])->assertSessionHasNoErrors();

    expect(PermitReviewRequest::query()->pluck('permit_id')->sort()->values()->all())->toBe(collect($chosen)->sort()->values()->all())
        ->and(PermitReviewRequest::query()->pluck('reviewer_id')->unique()->all())->toBe([$this->reviewer->id])
        ->and(PermitReviewRequest::query()->pluck('requested_by')->unique()->all())->toBe([$this->approver->id]);

    // Sending again does not ask the same person twice.
    sendForReview($chosen, [$this->reviewer->id])->assertSessionHasNoErrors();
    expect(PermitReviewRequest::query()->count())->toBe(2);

    // The reviewer's queue holds what was sent to them; another reviewer's holds none of it.
    $this->actingAs($this->reviewer)
        ->get(route('staff.permits.index'))
        ->assertInertia(fn (Assert $page) => $page->has('permits.data', 2));

    $this->actingAs($this->otherReviewer)
        ->get(route('staff.permits.index'))
        ->assertInertia(fn (Assert $page) => $page->has('permits.data', 0));
});

it('lets a reviewer give an opinion only on what was sent to them', function () {
    $sent = $this->permits[0]->id;
    sendForReview([$sent], [$this->reviewer->id])->assertSessionHasNoErrors();

    $this->actingAs($this->reviewer)
        ->get(route('letters.show', $this->letter))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.recommend', true)
            ->where('can.decide', false)
            ->where('can.sendForReview', false)
            ->where('reviewable', [$sent]));

    $this->actingAs($this->otherReviewer)
        ->get(route('letters.show', $this->letter))
        ->assertInertia(fn (Assert $page) => $page->where('can.recommend', false));

    $this->actingAs($this->otherReviewer)
        ->post(route('letters.recommend', $this->letter), ['permits' => [$sent], 'recommendation' => 'approve', 'note' => 'Looks fine.'])
        ->assertForbidden();

    $this->actingAs($this->reviewer)
        ->post(route('letters.recommend', $this->letter), ['permits' => [$this->permits[1]->id], 'recommendation' => 'approve', 'note' => 'Looks fine.'])
        ->assertForbidden();

    $this->actingAs($this->reviewer)
        ->post(route('letters.recommend', $this->letter), ['permits' => [$sent], 'recommendation' => 'reject', 'note' => 'The route crosses the centre.'])
        ->assertSessionHasNoErrors();

    // The person who decides sees the opinion and who has answered.
    $this->actingAs($this->approver)
        ->get(route('letters.show', $this->letter))
        ->assertInertia(fn (Assert $page) => $page
            ->has('review.opinions', 1)
            ->where('review.opinions.0.reviewer', $this->reviewer->name)
            ->where('review.opinions.0.recommendation', 'reject')
            ->where('review.opinions.0.note', 'The route crosses the centre.')
            ->where('review.opinions.0.vehicles', 1)
            ->where('review.sent', [['name' => $this->reviewer->name, 'answered' => true]]));
});

it('never makes the decision wait for an opinion', function () {
    sendForReview($this->permits->pluck('id')->all(), [$this->reviewer->id])->assertSessionHasNoErrors();

    // Nobody has answered; the approver approves anyway, straight from "submitted".
    $this->actingAs($this->approver)
        ->post(route('letters.approve', $this->letter), ['permits' => $this->permits->pluck('id')->all()])
        ->assertSessionHasNoErrors();

    expect(Permit::query()->pluck('status')->unique()->all())->toBe([PermitStatus::AwaitingPayment]);
});

it('asks only people who may review', function () {
    $cashier = staffUser(Role::Cashier);

    sendForReview([$this->permits[0]->id], [$cashier->id])->assertSessionHasErrors('reviewers');
    sendForReview([$this->permits[0]->id], [])->assertSessionHasErrors('reviewers');

    expect(PermitReviewRequest::query()->count())->toBe(0);
});

it('leaves sending for review to the person who decides', function () {
    $this->actingAs($this->reviewer)
        ->post(route('letters.send-for-review', $this->letter), [
            'permits' => [$this->permits[0]->id],
            'reviewers' => [$this->otherReviewer->id],
        ])
        ->assertForbidden();

    expect(PermitReviewRequest::query()->count())->toBe(0);
});
