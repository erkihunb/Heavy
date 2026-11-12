<?php

use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The cashier's queue and decisions (spec §11 payment verification screen),
 * and who may open a receipt file (spec §15: served through an authorised
 * controller only).
 */

beforeEach(function () {
    Storage::fake('documents');

    $this->cashier = staffUser(Role::Cashier);
    $this->permit = Permit::factory()->paymentSubmitted()->create();
    $this->payment = Payment::factory()->forPermit($this->permit)->create(['receipt_number' => 'FS100001']);
});

describe('queue', function () {
    it('lists payments waiting for verification, oldest first', function () {
        $older = Payment::factory()->create(['created_at' => now()->subDays(3), 'receipt_number' => 'FS100000']);
        Payment::factory()->verified()->create();
        Payment::factory()->rejected()->create();

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/payments/index')
                ->has('payments.data', 2)
                ->where('payments.data.0.id', $older->id)
                ->where('payments.data.1.id', $this->payment->id)
                ->where('filters.status', 'submitted')
                ->has('payments.data.0.permit.organization')
                ->where('payments.data.0.can.verify', true));
    });

    it('can show every payment, or one status', function () {
        Payment::factory()->verified()->create();
        Payment::factory()->rejected()->create();

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.index', ['status' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 3)->where('filters.status', 'all'));

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.index', ['status' => 'verified']))
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.status', 'verified'));
    });

    it('finds a payment by part of its receipt number, however it is typed', function () {
        Payment::factory()->create(['receipt_number' => 'CBE-55']);

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.index', ['receipt' => ' fs 1000 ']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('payments.data', 1)
                ->where('payments.data.0.receipt_number', 'FS100001')
                ->where('filters.receipt', 'FS1000'));
    });

    it('ignores filters that are not text', function () {
        $this->actingAs($this->cashier)
            ->get(route('staff.payments.index').'?status[]=verified&receipt[]=x')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
    });

    it('shows a payment with its permit and earlier payments', function () {
        Payment::factory()->forPermit($this->permit)->rejected()->create();

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.show', $this->payment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/payments/show')
                ->where('payment.id', $this->payment->id)
                ->where('payment.permit.fee.amount_cents', 100_000)
                ->has('payment.permit.payments', 2)
                ->where('approvedByViewer', false)
                ->has('payment.submitted_by'));
    });

    it('tells the approver of the permit that someone else must verify', function () {
        $this->cashier->assignRole(Role::Approver->value);
        $this->permit->forceFill(['approved_by' => $this->cashier->id])->save();

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.show', $this->payment))
            ->assertInertia(fn (Assert $page) => $page->where('approvedByViewer', true));
    });
});

describe('decisions', function () {
    it('verifies a payment and sends the application on to the Issuer', function () {
        $this->actingAs($this->cashier)
            ->post(route('staff.payments.verify', $this->payment), ['note' => ''])
            ->assertRedirect(route('staff.payments.show', $this->payment))
            ->assertSessionHas('success', __('payments.messages.verified'));

        expect($this->permit->refresh()->status)->toBe(PermitStatus::PaymentConfirmed)
            ->and($this->permit->issued_at)->toBeNull()
            ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Verified)
            ->and($this->payment->notes)->toBeNull();
    });

    it('rejects a payment with a reason', function () {
        $this->actingAs($this->cashier)
            ->post(route('staff.payments.reject', $this->payment), ['note' => 'Amount on the receipt is 1,000 ETB short.'])
            ->assertRedirect(route('staff.payments.show', $this->payment));

        expect($this->permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Rejected)
            ->and($this->payment->notes)->toBe('Amount on the receipt is 1,000 ETB short.');
    });

    it('requires a reason to reject', function () {
        $this->actingAs($this->cashier)
            ->post(route('staff.payments.reject', $this->payment), ['note' => '   '])
            ->assertSessionHasErrors(['note' => __('permits.errors.note_required')]);

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Submitted);
    });

    it('explains instead of failing when the payment was already decided', function () {
        $this->actingAs($this->cashier)->post(route('staff.payments.verify', $this->payment));

        $this->actingAs(staffUser(Role::Cashier))
            ->from(route('staff.payments.show', $this->payment))
            ->post(route('staff.payments.reject', $this->payment), ['note' => 'Late.'])
            ->assertRedirect(route('staff.payments.show', $this->payment))
            ->assertSessionHas('warning');

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Verified);
    });

    it('does not let the approver of the permit verify its payment', function () {
        $this->cashier->assignRole(Role::Approver->value);
        $this->permit->forceFill(['approved_by' => $this->cashier->id])->save();

        $this->actingAs($this->cashier)
            ->from(route('staff.payments.show', $this->payment))
            ->post(route('staff.payments.verify', $this->payment))
            ->assertSessionHas('warning', __('permits.errors.verifier_is_approver'));

        expect($this->permit->refresh()->status)->toBe(PermitStatus::PaymentSubmitted);
    });

    it('lets Admins verify too, under the same separation of duties', function () {
        $admin = staffUser(Role::Admin);

        $this->actingAs($admin)->get(route('staff.payments.index'))->assertOk();
        $this->actingAs($admin)
            ->post(route('staff.payments.verify', $this->payment))
            ->assertSessionHas('success');

        expect($this->permit->refresh()->status)->toBe(PermitStatus::PaymentConfirmed);

        $permit = Permit::factory()->paymentSubmitted()->create(['approved_by' => $admin->id]);
        $payment = Payment::factory()->forPermit($permit)->create();

        $this->actingAs($admin)
            ->post(route('staff.payments.verify', $payment))
            ->assertSessionHas('warning', __('permits.errors.verifier_is_approver'));
    });

    it('keeps everyone but cashiers and Admins out', function (Role $role) {
        $user = $role === Role::Applicant ? applicantWithOrganization() : staffUser($role);

        $this->actingAs($user)->get(route('staff.payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.payments.show', $this->payment))->assertForbidden();
        $this->actingAs($user)->post(route('staff.payments.verify', $this->payment))->assertForbidden();
        $this->actingAs($user)->post(route('staff.payments.reject', $this->payment), ['note' => 'x'])->assertForbidden();

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Submitted);
    })->with([Role::Applicant, Role::Reviewer, Role::Approver, Role::TrafficOfficer]);

    it('requires a cashier to have confirmed two-factor authentication', function () {
        $this->actingAs(userWithRole(Role::Cashier))
            ->get(route('staff.payments.index'))
            ->assertRedirect(route('two-factor.show'));
    });
});

describe('receipt files', function () {
    beforeEach(function () {
        Storage::disk('documents')->put('receipts/abcdefghijklmnopqrstuvwxyz0123456789ABCD.pdf', "%PDF-1.4\n%%EOF\n");
        $this->payment->forceFill(['receipt_file_path' => 'receipts/abcdefghijklmnopqrstuvwxyz0123456789ABCD.pdf'])->save();
        $this->owner = applicantWithOrganization();
        $this->owner->forceFill(['organization_id' => $this->permit->organization_id])->save();
    });

    it('serves the receipt to its organization and to cashiers, without revealing where it is stored', function () {
        foreach ([[$this->owner, 'payments.receipt'], [$this->cashier, 'staff.payments.receipt']] as [$user, $route]) {
            $response = $this->actingAs($user)->get(route($route, $this->payment))->assertOk();

            expect($response->headers->get('Content-Disposition'))
                ->toContain('receipt-FS100001.pdf')
                ->not->toContain('receipts/')
                ->not->toContain('abcdefghij')
                ->and($response->headers->get('Cache-Control'))->toContain('no-store')
                ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
        }
    });

    it('pretends another organization\'s receipt does not exist', function () {
        $this->actingAs(applicantWithOrganization())
            ->get(route('payments.receipt', $this->payment))
            ->assertNotFound();
    });

    it('keeps other staff out', function (Role $role) {
        $user = staffUser($role);

        $this->actingAs($user)->get(route('payments.receipt', $this->payment))->assertNotFound();
        $this->actingAs($user)->get(route('staff.payments.receipt', $this->payment))->assertForbidden();
    })->with([Role::Reviewer, Role::Approver, Role::TrafficOfficer]);

    it('serves receipts to Admins, who hold every staff permission', function () {
        $this->actingAs(staffUser(Role::Admin))->get(route('staff.payments.receipt', $this->payment))->assertOk();
    });

    it('answers not found when there is no file', function () {
        $this->payment->forceFill(['receipt_file_path' => null])->save();

        $this->actingAs($this->cashier)->get(route('staff.payments.receipt', $this->payment))->assertNotFound();
    });

    it('sends guests to the login page', function () {
        $this->get(route('payments.receipt', $this->payment))->assertRedirect(route('login'));
    });
});
