<?php

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role;
use App\Models\Permit;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The audit log viewer (spec §11): Admins only, newest first, filtered by
 * event and by who acted.
 */

beforeEach(function () {
    $this->admin = staffUser(Role::Admin);
});

it('is only for admins', function () {
    $this->actingAs(staffUser(Role::Approver))->get(route('admin.audit-log.index'))->assertForbidden();
});

it('lists entries newest first with who, what and the change', function () {
    $permit = Permit::factory()->create(['permit_number' => 'TMA-HV-2019-000123']);
    $cashier = staffUser(Role::Cashier);
    $cashier->forceFill(['name' => 'Selam Cashier'])->save();

    app(AuditLog::class)->record(AuditEvent::PermitTransition, $permit, $cashier, old: ['status' => 'submitted'], new: ['status' => 'under_review']);
    app(AuditLog::class)->record(AuditEvent::SettingsUpdated, actor: null, new: ['app.name' => 'X']);

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit-log/index')
            ->has('entries.data', 2)
            ->where('entries.data.0.event', 'settings.updated')
            ->where('entries.data.0.actor', null)
            ->where('entries.data.1.event', 'permit.transition')
            ->where('entries.data.1.actor.name', 'Selam Cashier')
            ->where('entries.data.1.subject.type', 'permit')
            ->where('entries.data.1.subject.label', 'TMA-HV-2019-000123')
            ->where('entries.data.1.old.status', 'submitted')
            ->where('entries.data.1.new.status', 'under_review'));
});

it('filters by event and by who acted', function () {
    $cashier = staffUser(Role::Cashier);
    $cashier->forceFill(['name' => 'Selam Cashier'])->save();

    app(AuditLog::class)->record(AuditEvent::SettingsUpdated, actor: $this->admin, new: ['a' => 1]);
    app(AuditLog::class)->record(AuditEvent::PaymentVerified, actor: $cashier, new: ['status' => 'verified']);

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index', ['event' => 'payment.verified']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('filters.event', 'payment.verified'));

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index', ['actor' => 'selam']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.data.0.event', 'payment.verified'));

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index', ['event' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 2)->where('filters.event', null));
});
