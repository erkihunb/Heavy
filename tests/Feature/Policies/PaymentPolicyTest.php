<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;

/*
 * Role × action matrix for payments, and the new permit ability to pay.
 * Applicants see the payments of their own organization; cashiers (and Admins,
 * who hold every staff permission) see and decide on all of them.
 */

const PAYMENT_ABILITIES = ['view', 'downloadReceipt', 'verify', 'reject'];

/**
 * A user with the role who belongs to the organization of the payment's
 * permit, so that only the role decides.
 */
function paymentMember(Role $role, Payment $payment): User
{
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => $payment->permit->organization_id])->save();

    return $user;
}

/**
 * @return list<string>
 */
function paymentAbilities(User $user, Payment $payment): array
{
    return array_values(array_filter(PAYMENT_ABILITIES, fn (string $ability): bool => $user->can($ability, $payment)));
}

it('gives each role exactly its abilities on a payment of its own organization', function (Role $role) {
    $payment = Payment::factory()->create();

    $expected = match ($role) {
        Role::Applicant => ['view', 'downloadReceipt'],
        Role::Cashier, Role::Admin => PAYMENT_ABILITIES,
        default => [],
    };

    expect(paymentAbilities(paymentMember($role, $payment), $payment))->toBe($expected);
})->with(Role::cases());

it('lets only cashiers and Admins reach the payment queue', function (Role $role) {
    expect(userWithRole($role)->can('viewQueue', Payment::class))
        ->toBe(in_array($role, [Role::Cashier, Role::Admin], true));
})->with(Role::cases());

it('gives an applicant nothing on another organization\'s payment, as not found', function () {
    $payment = Payment::factory()->create();
    $outsider = applicantWithOrganization();

    expect(paymentAbilities($outsider, $payment))->toBe([]);

    foreach (PAYMENT_ABILITIES as $ability) {
        expect(Gate::forUser($outsider)->inspect($ability, $payment)->status())->toBe(404);
    }
});

it('gives an applicant without an organization nothing', function () {
    $payment = Payment::factory()->create();

    expect(paymentAbilities(userWithRole(Role::Applicant), $payment))->toBe([]);
});

describe('paying for a permit', function () {
    beforeEach(function () {
        $this->applicant = applicantWithOrganization();
        $this->vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    });

    it('is allowed only while the permit waits for payment', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status, 'fee_cents' => 100_000]);

        expect($this->applicant->can('submitPayment', $permit))->toBe($status === PermitStatus::AwaitingPayment);
    })->with(PermitStatus::cases());

    it('is for the permit\'s own organization only', function () {
        $permit = Permit::factory()->awaitingPayment()->create();

        expect(Gate::forUser($this->applicant)->inspect('submitPayment', $permit)->status())->toBe(404);
    });

    it('is not for staff', function (Role $role) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create();
        $staff = userWithRole($role);
        $staff->forceFill(['organization_id' => $permit->organization_id])->save();

        expect($staff->can('submitPayment', $permit))->toBeFalse();
    })->with(Role::staff());
});
