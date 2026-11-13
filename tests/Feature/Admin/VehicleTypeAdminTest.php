<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Role;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Vehicle types (spec §11): created, renamed and retired by an Admin;
 * cached for applicants, the cache dropped on every change.
 */

beforeEach(function () {
    $this->admin = staffUser(Role::Admin);
});

it('is only for admins', function () {
    $this->actingAs(staffUser(Role::Approver))->get(route('admin.vehicle-types.index'))->assertForbidden();
});

it('lists the types with how many vehicles have them', function () {
    $type = VehicleType::factory()->create(['sort_order' => 1]);
    Vehicle::factory()->count(2)->create(['vehicle_type_id' => $type->id]);
    VehicleType::factory()->inactive()->create(['sort_order' => 2]);

    $this->actingAs($this->admin)
        ->get(route('admin.vehicle-types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/vehicle-types/index')
            ->has('types', 2)
            ->where('types.0.id', $type->id)
            ->where('types.0.vehicles', 2)
            ->where('types.1.is_active', false));
});

it('creates a type and audits it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.vehicle-types.store'), [
            'code' => ' Tanker ',
            'name_am' => 'ታንከር',
            'name_en' => 'Tanker',
            'is_active' => true,
            'sort_order' => 5,
        ])
        ->assertRedirect(route('admin.vehicle-types.index'));

    $type = VehicleType::query()->where('code', 'tanker')->sole();

    expect($type->name_am)->toBe('ታንከር')->and($type->is_active)->toBeTrue();
    expect(Activity::query()->where('event', AuditEvent::VehicleTypeCreated->value)->sole()->properties['attributes']['code'])->toBe('tanker');
});

it('keeps the code once created, and retires a type', function () {
    $type = VehicleType::factory()->create(['code' => 'crane']);

    $this->actingAs($this->admin)
        ->put(route('admin.vehicle-types.update', $type), [
            'code' => 'changed',
            'name_am' => 'ክሬን',
            'name_en' => 'Crane',
            'is_active' => false,
            'sort_order' => 3,
        ])
        ->assertRedirect();

    $type->refresh();
    expect($type->code)->toBe('crane')->and($type->is_active)->toBeFalse();

    $entry = Activity::query()->where('event', AuditEvent::VehicleTypeUpdated->value)->sole();
    expect($entry->properties['old']['is_active'])->toBeTrue()
        ->and($entry->properties['attributes']['is_active'])->toBeFalse();
});

it('validates the type', function (array $input, string $field) {
    VehicleType::factory()->create(['code' => 'cargo']);

    $this->actingAs($this->admin)
        ->post(route('admin.vehicle-types.store'), ['code' => 'new_type', 'name_am' => 'አ', 'name_en' => 'A', 'sort_order' => 0, ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'taken code' => [['code' => 'cargo'], 'code'],
    'code with spaces' => [['code' => 'two words'], 'code'],
    'no Amharic name' => [['name_am' => ''], 'name_am'],
    'negative order' => [['sort_order' => -1], 'sort_order'],
]);

it('offers only active types on the office\'s vehicle form, from a cache that a change refreshes', function () {
    $active = VehicleType::factory()->create();
    $retired = VehicleType::factory()->inactive()->create();
    $applicant = applicantWithOrganization();
    $registrar = staffUser(Role::Registrar);

    $types = fn (): array => $this->actingAs($registrar)
        ->get(route('admin.applicants.vehicles.create', $applicant))
        ->viewData('page')['props']['vehicleTypes'];

    expect(collect($types())->pluck('id')->all())->toContain($active->id)->not->toContain($retired->id)
        ->and(Cache::has(VehicleType::CACHE_KEY))->toBeTrue();

    $retired->update(['is_active' => true]);

    expect(Cache::has(VehicleType::CACHE_KEY))->toBeFalse()
        ->and(collect($types())->pluck('id')->all())->toContain($retired->id);
});
