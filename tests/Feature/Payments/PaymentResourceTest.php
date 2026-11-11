<?php

use App\Enums\Role;
use App\Http\Resources\FeeScheduleResource;
use App\Http\Resources\PaymentResource;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * The exact payment fields the browser receives. The stored file path never
 * leaves the server; who handled a payment is for cashiers only; a cashier's
 * remark reaches the applicant only when it explains a rejection.
 */

const PAYMENT_FIELDS = [
    'id', 'permit_id', 'method', 'receipt_number', 'amount', 'paid_at', 'status',
    'notes', 'has_receipt_file', 'submitted_at', 'decided_at', 'can',
];

const PAYMENT_CASHIER_FIELDS = ['submitted_by', 'decided_by'];

/**
 * @return array<string, mixed>
 */
function paymentResourceAs(?User $viewer, JsonResource $resource): array
{
    $request = Request::create('/');
    app()->instance('request', $request);
    $request->setUserResolver(fn () => $viewer);

    return $resource->response($request)->getData(true);
}

/**
 * @param  list<string>  ...$lists
 * @return list<string>
 */
function sortedPaymentFields(array ...$lists): array
{
    $fields = array_merge(...$lists);
    sort($fields);

    return $fields;
}

beforeEach(function () {
    $this->payment = Payment::factory()
        ->verified()
        ->create(['receipt_file_path' => 'receipts/secret-name.pdf', 'notes' => 'Stamp checked against the bank list.'])
        ->load(['submitter', 'verifier']);

    $this->applicant = userWithRole(Role::Applicant);
    $this->applicant->forceFill(['organization_id' => $this->payment->permit->organization_id])->save();

    // The permit is sent only when the controller loads it.
    $this->payment->unsetRelation('permit');
});

it('includes the permit when it was loaded', function () {
    $data = paymentResourceAs(userWithRole(Role::Cashier), PaymentResource::make($this->payment->load('permit')));

    expect($data['permit']['id'])->toBe($this->payment->permit_id);
});

it('gives the applicant exactly the public fields', function () {
    $data = paymentResourceAs($this->applicant, PaymentResource::make($this->payment));
    $keys = array_keys($data);
    sort($keys);

    expect($keys)->toBe(sortedPaymentFields(PAYMENT_FIELDS))
        ->and($data['has_receipt_file'])->toBeTrue()
        // A remark on a verified payment is internal.
        ->and($data['notes'])->toBeNull()
        ->and($data['amount'])->toBe(['amount_cents' => 100_000, 'currency' => 'ETB', 'formatted' => 'ETB 1,000.00'])
        ->and($data['can'])->toBe(['verify' => false, 'reject' => false]);
});

it('gives cashiers and Admins the handling fields as well', function (Role $role) {
    $data = paymentResourceAs(userWithRole($role), PaymentResource::make($this->payment));
    $keys = array_keys($data);
    sort($keys);

    expect($keys)->toBe(sortedPaymentFields(PAYMENT_FIELDS, PAYMENT_CASHIER_FIELDS))
        ->and($data['notes'])->toBe('Stamp checked against the bank list.')
        ->and($data['decided_by'])->toBe($this->payment->verifier->name);
})->with([Role::Cashier, Role::Admin]);

it('shows the applicant why a payment was rejected', function () {
    $rejected = Payment::factory()->forPermit($this->payment->permit)->rejected(note: 'የማይነበብ ደረሰኝ')->create();

    expect(paymentResourceAs($this->applicant, PaymentResource::make($rejected))['notes'])->toBe('የማይነበብ ደረሰኝ');
});

it('never sends the stored path of the receipt', function (?Role $role) {
    $json = json_encode(paymentResourceAs($role === null ? null : userWithRole($role), PaymentResource::make($this->payment)));

    expect($json)->not->toContain('secret-name')
        ->not->toContain('receipts/')
        ->not->toContain('receipt_file_path');
})->with([Role::Applicant, Role::Cashier, Role::Admin, null]);

it('offers decisions only on a payment waiting for verification', function () {
    $waiting = Payment::factory()->create();
    $cashier = userWithRole(Role::Cashier);

    expect(paymentResourceAs($cashier, PaymentResource::make($waiting))['can'])->toBe(['verify' => true, 'reject' => true])
        ->and(paymentResourceAs($cashier, PaymentResource::make($this->payment))['can'])->toBe(['verify' => false, 'reject' => false]);
});

describe('FeeScheduleResource', function () {
    it('sends a line in both calendars with the amount', function () {
        $type = VehicleType::factory()->create();
        $line = FeeSchedule::factory()->forVehicleType($type)->amount(150_050)->effective('2025-09-11', '2026-09-10')->create()->load('vehicleType');

        $data = paymentResourceAs(userWithRole(Role::Admin), FeeScheduleResource::make($line));

        expect(array_keys($data))->toBe([
            'id', 'permit_type', 'vehicle_type', 'vehicle_type_id', 'min_days', 'max_days',
            'amount', 'effective_from', 'effective_to', 'is_current',
        ])
            ->and($data['vehicle_type']['id'])->toBe($type->id)
            ->and($data['amount']['formatted'])->toBe('ETB 1,500.50')
            ->and($data['effective_from']['ethiopian'])->toBe('01/01/2018')
            ->and($data['effective_to']['ethiopian'])->toBe('05/13/2018');
    });

    it('sends null, not an empty object, for a line covering every vehicle type', function () {
        $line = FeeSchedule::factory()->create()->load('vehicleType');

        expect(paymentResourceAs(userWithRole(Role::Admin), FeeScheduleResource::make($line))['vehicle_type'])->toBeNull();
    });
});
