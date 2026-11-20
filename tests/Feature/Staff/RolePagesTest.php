<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\Role as RoleModel;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * One page per role: the application page is read only and links each
 * member of staff to their own page — review and decision, payment, issue
 * and print. The Registrar enters a payment on its own page, only once the
 * application is approved, without opening the application for editing.
 */

beforeEach(function () {
    Storage::fake('documents');
});

it('keeps the application page read only and links only to pages with something to do', function (Role $role, array $desks) {
    // Issued: nothing waits for anyone, so every role sees the read-only page, with no
    // dead links; whoever may revoke it is offered that, marked as not waiting.
    $permit = Permit::factory()->issued()->create();

    $this->actingAs(staffUser($role))
        ->get(route('staff.permits.show', $permit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/permits/show')
            ->where('direct', false)
            ->where('desks', fn ($given) => collect($given)->pluck('key')->all() === $desks));
})->with([
    'reviewer' => [Role::Reviewer, []],
    'approver' => [Role::Approver, ['decision']],
    'cashier' => [Role::Cashier, []],
    'issuer' => [Role::Issuer, []],
    'supervisor' => [Role::Supervisor, []],
]);

it('offers revoking an issued permit without saying it waits for the person', function () {
    $permit = Permit::factory()->issued()->create();

    $this->actingAs(staffUser(Role::Approver))
        ->get(route('staff.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('desks.0.key', 'decision')
            ->where('desks.0.pending', false)
            ->where('desks.0.afterIssue', true));
});

it('opens the work waiting for the person directly on the application page', function (PermitStatus $status, Role $role, string $component) {
    $permit = Permit::factory()->awaitingPayment()->create(['status' => $status, 'fee_cents' => 100_000]);

    $this->actingAs(staffUser($role))
        ->get(route('staff.permits.show', $permit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('direct', true));
})->with([
    'a decision for the Reviewer' => [PermitStatus::Submitted, Role::Reviewer, 'staff/permits/decision'],
    'a payment for the Cashier' => [PermitStatus::AwaitingPayment, Role::Cashier, 'staff/permits/payment'],
    'issuing for the Issuer' => [PermitStatus::PaymentConfirmed, Role::Issuer, 'staff/permits/issue'],
]);

it('shows the read-only page when nothing waits for the person', function () {
    // Submitted: waiting for a review, not for the Cashier.
    $permit = Permit::factory()->submitted()->create();

    $this->actingAs(staffUser(Role::Cashier))
        ->get(route('staff.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/permits/show')
            // No link to the payment page: there is nothing to do there yet.
            ->where('desks', []));
});

it('marks the page where something waits for the person now', function () {
    $permit = Permit::factory()->submitted()->create();

    $this->actingAs(staffUser(Role::Reviewer))
        ->get(route('staff.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('desks.0.key', 'decision')
            ->where('desks.0.pending', true)
            ->where('desks.0.url', route('staff.permits.decision', $permit)));
});

it('opens each role page only to its role', function (string $route, string $component, Role $allowed, Role $refused) {
    $permit = Permit::factory()->submitted()->create();

    $this->actingAs(staffUser($allowed))
        ->get(route($route, $permit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));

    $this->actingAs(staffUser($refused))->get(route($route, $permit))->assertForbidden();
})->with([
    'decision' => ['staff.permits.decision', 'staff/permits/decision', Role::Reviewer, Role::Cashier],
    'payment' => ['staff.permits.payment-desk', 'staff/permits/payment', Role::Cashier, Role::Reviewer],
    'issue' => ['staff.permits.issue-desk', 'staff/permits/issue', Role::Issuer, Role::Approver],
]);

it('lets the cashier take a payment only once the application awaits it', function () {
    $cashier = staffUser(Role::Cashier);

    $this->actingAs($cashier)
        ->get(route('staff.permits.payment-desk', Permit::factory()->submitted()->create()))
        ->assertInertia(fn (Assert $page) => $page->where('permit.can.recordPayment', false));

    $this->actingAs($cashier)
        ->get(route('staff.permits.payment-desk', Permit::factory()->awaitingPayment()->create(['fee_cents' => 100_000])))
        ->assertInertia(fn (Assert $page) => $page->where('permit.can.recordPayment', true));
});

describe('the Registrar', function () {
    beforeEach(function () {
        $this->registrar = staffUser(Role::Registrar);
        $this->applicant = applicantWithOrganization();
        $this->vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    });

    it('takes no payment: the cashier does, on the letter\'s page', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create(['fee_cents' => 600_050, 'applicant_id' => $this->applicant->id]);

        $this->actingAs($this->registrar)
            ->get(route('admin.permits.show', $permit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('actions', ['update' => false, 'submit' => false, 'delete' => false]));
    });

    it('has no edit page after the submission', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create(['applicant_id' => $this->applicant->id]);

        $this->actingAs($this->registrar)
            ->get(route('admin.permits.edit', $permit))
            ->assertForbidden();
    });
});

it('shows a person who holds the permissions of several desks the work of all of them', function () {
    // A role made on the Roles screen: its name says nothing, its permissions decide.
    $role = RoleModel::query()->create(['name' => 'counter', 'guard_name' => 'web']);
    $role->givePermissionTo(['permits.view-queue', 'payments.verify', 'permits.issue']);
    $user = staffUser(Role::Cashier);
    $user->syncRoles(['counter']);

    $review = Permit::factory()->submitted()->create();
    $toPay = Permit::factory()->awaitingPayment()->create();
    $toIssue = Permit::factory()->awaitingPayment()->create(['status' => PermitStatus::PaymentConfirmed]);

    $this->actingAs($user)
        ->get(route('staff.permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permits.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
                === collect([$toPay->id, $toIssue->id])->sort()->values()->all()));

    // Each application links to the page where their work on it waits, and only that one.
    $this->actingAs($user)
        ->get(route('staff.permits.show', $toPay))
        ->assertInertia(fn (Assert $page) => $page
            ->where('desks', fn ($given) => collect($given)->pluck('key')->all() === ['payment']));
    $this->actingAs($user)
        ->get(route('staff.permits.show', $toIssue))
        ->assertInertia(fn (Assert $page) => $page
            ->where('desks', fn ($given) => collect($given)->pluck('key')->all() === ['issue']));

    $this->actingAs($user)->get(route('staff.permits.decision', $review))->assertForbidden();
});
