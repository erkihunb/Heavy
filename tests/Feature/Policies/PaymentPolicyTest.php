<?php

use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;

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
