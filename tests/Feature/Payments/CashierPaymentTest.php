<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The record of payments the cashier took (read only: payments are recorded
 * on the letter's page), and who may open a receipt file (spec §15: served
 * through an authorised controller only).
 */

beforeEach(function () {
    Storage::fake('documents');

    $this->cashier = staffUser(Role::Cashier);
    $this->permit = Permit::factory()->awaitingPayment()->create(['status' => PermitStatus::PaymentConfirmed]);
    $this->payment = Payment::factory()->forPermit($this->permit)->verified()->create(['receipt_number' => 'FS100001']);
});

describe('record', function () {
    it('lists every payment, newest first', function () {
        $older = Payment::factory()->verified()->create(['created_at' => now()->subDays(3), 'receipt_number' => 'FS100000']);

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/payments/index')
                ->has('payments.data', 2)
                ->where('payments.data.0.id', $this->payment->id)
                ->where('payments.data.1.id', $older->id)
                ->where('filters.status', 'all')
                ->has('payments.data.0.permit.organization'));
    });

    it('can show one status', function () {
        Payment::factory()->rejected()->create();

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.index', ['status' => 'verified']))
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.status', 'verified'));
    });

    it('finds a payment by part of its receipt number, however it is typed', function () {
        Payment::factory()->verified()->create(['receipt_number' => 'CBE-55']);

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

    it('shows a payment with its permit and earlier payments, with nothing to decide', function () {
        Payment::factory()->forPermit($this->permit)->rejected()->create();

        $this->actingAs($this->cashier)
            ->get(route('staff.payments.show', $this->payment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/payments/show')
                ->where('payment.id', $this->payment->id)
                ->where('payment.permit.fee.amount_cents', 100_000)
                ->has('payment.permit.payments', 2)
                ->missing('payment.can')
                ->missing('approvedByViewer')
                ->has('payment.submitted_by'));
    });

    it('has no verify or reject step any more', function () {
        expect(Route::has('staff.payments.verify'))->toBeFalse()
            ->and(Route::has('staff.payments.reject'))->toBeFalse()
            ->and(Route::has('admin.permits.payment'))->toBeFalse()
            ->and(Route::has('admin.permits.payments.store'))->toBeFalse();
    });

    it('keeps everyone but cashiers and Admins out', function (Role $role) {
        $user = staffUser($role);

        $this->actingAs($user)->get(route('staff.payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.payments.show', $this->payment))->assertForbidden();
    })->with([Role::Reviewer, Role::Approver, Role::TrafficOfficer]);

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

    it('serves the receipt to cashiers, without revealing where it is stored', function () {
        $response = $this->actingAs($this->cashier)->get(route('staff.payments.receipt', $this->payment))->assertOk();

        expect($response->headers->get('Content-Disposition'))
            ->toContain('receipt-FS100001.pdf')
            ->not->toContain('receipts/')
            ->not->toContain('abcdefghij')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    });

    it('has no address for applicants: receipts are opened at the office only', function () {
        $this->get('/payments/'.$this->payment->id.'/receipt')->assertNotFound();
        assertSignedOut($this->actingAs($this->owner)->get(route('staff.payments.receipt', $this->payment)));
    });

    it('keeps other staff out', function (Role $role) {
        $user = staffUser($role);

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
        $this->get(route('staff.payments.receipt', $this->payment))->assertRedirect(route('login'));
    });
});
