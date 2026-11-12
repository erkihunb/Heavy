<?php

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The audit log viewer (spec §11): Admins only, newest first, filtered by
 * event, by who acted, by what it concerns and by date.
 */

beforeEach(function () {
    $this->admin = staffUser(Role::Admin);
});

it('is only for admins', function () {
    $this->actingAs(staffUser(Role::Approver))->get(route('admin.audit-log.index'))->assertForbidden();
});

it('lists entries newest first with who, what and the change', function () {
    $permit = Permit::factory()->create(['permit_number' => 'AACRA-2019-000123']);
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
            ->where('entries.data.1.subject.label', 'AACRA-2019-000123')
            ->where('entries.data.1.changes.0.key', 'status')
            ->where('entries.data.1.changes.0.label', __('admin.audit.fields.status'))
            ->where('entries.data.1.changes.0.old', __('permits.status.submitted'))
            ->where('entries.data.1.changes.0.new', __('permits.status.under_review'))
            ->where('entries.data.1.changes.0.had_old', true)
            ->where('entries.data.0.changes.0.key', 'app.name')
            ->where('entries.data.0.changes.0.had_old', false));
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

it('groups the events by area for the event filter', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('eventGroups.permit', fn ($events): bool => collect($events)->contains('permit.document_downloaded'))
            ->where('eventGroups.settings', ['settings.updated'])
            ->where('filters', ['event' => null, 'actor' => '', 'subject' => '', 'from' => null, 'to' => null]));
});

it('finds the entries about a permit by its number or its plate', function () {
    $vehicle = Vehicle::factory()->create();
    $permit = Permit::factory()->forVehicles($vehicle)->create(['permit_number' => 'AACRA-2019-000555']);
    $other = Permit::factory()->create(['permit_number' => 'AACRA-2019-000999']);

    app(AuditLog::class)->record(AuditEvent::PermitTransition, $permit, $this->admin, new: ['status' => 'submitted']);
    app(AuditLog::class)->record(AuditEvent::PermitTransition, $other, $this->admin, new: ['status' => 'submitted']);
    app(AuditLog::class)->record(AuditEvent::SettingsUpdated, actor: $this->admin, new: ['a' => 1]);

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index', ['subject' => '000555']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)
            ->where('entries.data.0.subject.label', 'AACRA-2019-000555')
            ->where('filters.subject', '000555'));

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index', ['subject' => $vehicle->plate_normalized]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)
            ->where('entries.data.0.subject.label', 'AACRA-2019-000555'));
});

it('filters by the day it happened, and ignores a date it cannot read', function () {
    // The log is append-only, so the older entry is written ten days ago.
    $this->travel(-10)->days();
    app(AuditLog::class)->record(AuditEvent::SettingsUpdated, actor: $this->admin, new: ['a' => 1]);
    $this->travelBack();
    app(AuditLog::class)->record(AuditEvent::SettingsUpdated, actor: $this->admin, new: ['a' => 2]);

    $today = now()->setTimezone((string) config('localization.display_timezone'))->toDateString();

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index', ['from' => $today]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('filters.from', $today));

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index', ['to' => now()->subDays(5)->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index', ['from' => 'yesterday']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 2)->where('filters.from', null));
});

it('labels the context recorded with an entry and names its values', function () {
    $permit = Permit::factory()->create();

    app(AuditLog::class)->record(AuditEvent::DocumentDownloaded, $permit, $this->admin, context: ['language' => 'en', 'purpose' => 'print']);

    $this->actingAs($this->admin)
        ->get(route('admin.audit-log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.changes', [])
            ->where('entries.data.0.context.0.label', __('admin.audit.fields.language'))
            ->where('entries.data.0.context.0.value', __('admin.audit.values.language.en'))
            ->where('entries.data.0.context.1.value', __('admin.audit.values.purpose.print')));
});
