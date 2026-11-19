<?php

use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The owner's four requests of 2026-11-19: applicants' accounts are not
 * users of the system; seeing issued permits is a permission; a returned
 * letter is corrected by the Registrar; and the queue opens a letter's page,
 * where the person who decides may send it for review.
 */

beforeEach(function () {
    Notification::fake();
    withFeeSchedule();
    $this->registrar = staffUser(Role::Registrar);
    $this->type = VehicleType::factory()->create();
    $this->otherType = VehicleType::factory()->create();
    $this->letter = registeredLetter();
    $this->permits = $this->letter->permits()->orderBy('id')->get();
    $this->approver = staffUser(Role::Approver);
});

it('leaves applicants out of the Users list and its count', function () {
    $applicant = User::query()->where('email', 'abebe@example.test')->sole();
    expect($applicant->isApplicant())->toBeTrue();

    $this->actingAs(staffUser(Role::Admin))
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($applicant->id))
            ->where('users.meta.total', User::query()->exceptApplicants()->count()));

    expect(User::query()->exceptApplicants()->pluck('id'))->not->toContain($applicant->id)
        ->and(User::query()->count())->toBeGreaterThan(User::query()->exceptApplicants()->count());
});

it('shows issued permits only to those who may see them', function () {
    $issued = Permit::factory()->issued()->create();
    $role = RoleModel::findByName(Role::Approver->value);

    $this->actingAs($this->approver)->get(route('staff.permits.show', $issued))->assertOk();

    $role->revokePermissionTo(Permission::ViewIssuedPermits->value);
    $this->approver->refresh();

    $this->actingAs($this->approver)->get(route('staff.permits.show', $issued))->assertNotFound();
    $this->actingAs($this->approver)
        ->get(route('admin.permits.index', ['status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page->has('permits.data', 0));
});

it('lets the person who decides return a letter, and the Registrar correct and send it again', function () {
    $ids = $this->permits->pluck('id')->all();

    $this->actingAs($this->approver)
        ->get(route('letters.show', $this->letter))
        ->assertInertia(fn (Assert $page) => $page->where('can.return', true));

    $this->actingAs($this->approver)
        ->post(route('letters.return', $this->letter), ['permits' => $ids, 'note' => 'The scan is unreadable.'])
        ->assertSessionHasNoErrors();

    expect(Permit::query()->pluck('status')->unique()->all())->toBe([PermitStatus::ChangesRequested]);

    // The Registrar sees what to correct, may edit each vehicle and sends them all again.
    $this->actingAs($this->registrar)
        ->get(route('letters.show', $this->letter))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.returned', 3)
            ->where('summary.return_note', 'The scan is unreadable.')
            ->where('can.resubmit', true));

    $this->actingAs($this->registrar)->get(route('admin.permits.edit', $this->permits[0]))->assertOk();

    $this->actingAs($this->registrar)
        ->post(route('letters.resubmit', $this->letter))
        ->assertSessionHasNoErrors();

    expect(Permit::query()->pluck('status')->unique()->all())->toBe([PermitStatus::Submitted]);
});

it('shows a letter waiting for a decision as one row that opens the letter', function () {
    $this->actingAs($this->approver)
        ->get(route('staff.permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permits.data', 1)
            ->where('letterPayments', fn ($rows) => collect($rows)->first()['url'] === route('letters.show', $this->letter)
                && collect($rows)->first()['stage'] === 'review'
                && collect($rows)->first()['vehicles'] === 3));

    $this->actingAs($this->approver)
        ->get(route('letters.show', $this->letter))
        ->assertInertia(fn (Assert $page) => $page->where('can.sendForReview', true));
});
