<?php

use App\Actions\Permits\ApprovePermit;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Exceptions\PermitRuleViolation;
use App\Models\FeeSchedule;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/*
 * The fee is copied onto the permit when it is approved (spec §4.4), from the
 * schedule in force that day. Later changes to the schedule never change what
 * an approved permit owes.
 */

beforeEach(function () {
    $this->applicant = applicantWithOrganization();
    $this->vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    $this->reviewer = userWithRole(Role::Reviewer);
    $this->approver = userWithRole(Role::Approver);
    $this->permit = Permit::factory()->forVehicles($this->vehicle)->recommended(reviewer: $this->reviewer)->create();
});

it('copies the fee in force on the day of approval onto the permit', function () {
    $today = now(config('localization.display_timezone'))->toDateString();
    FeeSchedule::factory()->effective('2020-01-01', now()->subDays(2)->toDateString())->amount(100_000)->create();
    FeeSchedule::factory()->effective($today)->amount(150_000)->create();

    app(ApprovePermit::class)->handle($this->permit, $this->approver);

    expect($this->permit->refresh()->fee_cents)->toBe(150_000)
        ->and($this->permit->currency)->toBe('ETB');
});

it('keeps the fee of an approved permit when the schedule changes', function () {
    $line = FeeSchedule::factory()->amount(100_000)->create();
    app(ApprovePermit::class)->handle($this->permit, $this->approver);

    $line->update(['amount_cents' => 900_000]);
    FeeSchedule::factory()->forVehicleType($this->vehicle->vehicle_type_id)->amount(5_000_000)->create();

    expect($this->permit->refresh()->fee_cents)->toBe(100_000);
});

it('refuses to approve a permit the schedule has no fee for, and changes nothing', function () {
    $sequences = DB::table('permit_number_sequences')->count();

    expect(fn () => app(ApprovePermit::class)->handle($this->permit, $this->approver))
        ->toThrow(fn (PermitRuleViolation $e) => expect($e->rule)->toBe('fee_not_configured'));

    $this->permit->refresh();

    expect($this->permit->status)->toBe(PermitStatus::UnderReview)
        ->and($this->permit->permit_number)->toBeNull()
        ->and($this->permit->fee_cents)->toBeNull()
        ->and($this->permit->approved_by)->toBeNull()
        ->and(PermitStatusTransition::query()->where('permit_id', $this->permit->id)->count())->toBe(0)
        ->and(DB::table('permit_number_sequences')->count())->toBe($sequences)
        ->and(DB::table('permit_vehicles')->where('permit_id', $this->permit->id)->value('is_blocking'))->toBeFalse();
});

it('tells the approver over HTTP when the fee is missing', function () {
    $approver = staffUser(Role::Approver);
    $permit = Permit::factory()->forVehicles($this->vehicle)->recommended(reviewer: $this->reviewer)->create();

    $this->actingAs($approver)
        ->from(route('staff.permits.show', $permit))
        ->post(route('staff.permits.approve', $permit))
        ->assertRedirect(route('staff.permits.show', $permit))
        ->assertSessionHas('warning', __('permits.errors.fee_not_configured'));

    expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview);
});

it('shows the fee on the permit once approved', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create(['fee_cents' => 600_050]);

    $this->actingAs($this->applicant)
        ->get(route('permits.show', $permit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('permit.fee', [
                'amount_cents' => 600_050,
                'currency' => 'ETB',
                'formatted' => 'ETB 6,000.50',
            ]));
});

it('has no fee before approval', function () {
    $this->actingAs($this->applicant)
        ->get(route('permits.show', $this->permit))
        ->assertInertia(fn ($page) => $page->where('permit.fee', null));
});
