<?php

use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = applicantWithOrganization();
    $this->otherOrganization = Organization::factory()->create();
    $this->theirVehicle = Vehicle::factory()->for($this->otherOrganization)->withPlate('ኢት-03-40134')->create([
        'owner_name' => 'የሌላ ተቋም ባለንብረት',
    ]);
});

it('never lists the vehicles of another organization', function () {
    $mine = Vehicle::factory()->for($this->user->organization)->withPlate('አአ-03-A45301')->create();

    $this->actingAs($this->user)
        ->get(route('vehicles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $mine->id)
            ->where('vehicles.meta.total', 1));
});

it('does not find the vehicles of another organization by searching for their plate', function () {
    $this->actingAs($this->user)
        ->get(route('vehicles.index', ['plate' => '40134']))
        ->assertInertia(fn (Assert $page) => $page->has('vehicles.data', 0));
});

it('answers 404 when opening the edit form of a vehicle of another organization', function () {
    $this->actingAs($this->user)
        ->get(route('vehicles.edit', $this->theirVehicle))
        ->assertNotFound();
});

it('answers 404 and changes nothing when updating a vehicle of another organization', function () {
    $this->actingAs($this->user)
        ->put(route('vehicles.update', $this->theirVehicle), [
            'plate' => 'ኢት-03-99999',
            'vehicle_type_id' => VehicleType::factory()->create()->id,
            'owner_name' => 'ተቀይሯል',
        ])
        ->assertNotFound();

    $this->theirVehicle->refresh();

    expect($this->theirVehicle->plate_normalized)->toBe('ኢት-03-40134')
        ->and($this->theirVehicle->owner_name)->toBe('የሌላ ተቋም ባለንብረት')
        ->and($this->theirVehicle->organization_id)->toBe($this->otherOrganization->id);
});

it('answers 404 for a vehicle that does not exist, exactly like one that belongs to someone else', function () {
    $this->actingAs($this->user)
        ->get(route('vehicles.edit', '01JZZZZZZZZZZZZZZZZZZZZZZZ'))
        ->assertNotFound();
});

it('cannot move a vehicle into another organization through the update request', function () {
    $mine = Vehicle::factory()->for($this->user->organization)->withPlate('አአ-03-A45301')->create();

    $this->actingAs($this->user)
        ->put(route('vehicles.update', $mine), [
            'plate' => 'አአ-03-A45301',
            'vehicle_type_id' => $mine->vehicle_type_id,
            'owner_name' => 'አበበ',
            'organization_id' => $this->otherOrganization->id,
        ])
        ->assertSessionHasNoErrors();

    expect($mine->refresh()->organization_id)->toBe($this->user->organization_id);
});
