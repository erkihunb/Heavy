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

it('keeps the application page read only and links each role to its own page', function (Role $role, array $desks) {
    $permit = Permit::factory()->submitted()->create();

    $this->actingAs(staffUser($role))
        ->get(route('staff.permits.show', $permit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/permits/show')
            ->where('desks', fn ($given) => collect($given)->pluck('key')->all() === $desks));
})->with([
    'reviewer' => [Role::Reviewer, ['decision']],
    'approver' => [Role::Approver, ['decision']],
    'cashier' => [Role::Cashier, ['payment']],
    'issuer' => [Role::Issuer, ['issue']],
    'supervisor' => [Role::Supervisor, []],
]);

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

    it('enters the payment on its own page once the application is approved', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create(['fee_cents' => 600_050, 'applicant_id' => $this->applicant->id]);

        $this->actingAs($this->registrar)
            ->get(route('admin.permits.show', $permit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('actions.submitPayment', true)
                ->where('actions.update', false)
                ->missing('paymentMethods'));

        $this->actingAs($this->registrar)
            ->get(route('admin.permits.payment', $permit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/permits/payment')
                ->has('paymentMethods'));
    });

    it('has no payment page before the approval, nor an edit page after the submission', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create(['applicant_id' => $this->applicant->id]);

        $this->actingAs($this->registrar)
            ->get(route('admin.permits.payment', $permit))
            ->assertForbidden();

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

    // Both role pages are theirs, and only those.
    $this->actingAs($user)
        ->get(route('staff.permits.show', $toPay))
        ->assertInertia(fn (Assert $page) => $page
            ->where('desks', fn ($given) => collect($given)->pluck('key')->all() === ['payment', 'issue']));

    $this->actingAs($user)->get(route('staff.permits.decision', $review))->assertForbidden();
});
