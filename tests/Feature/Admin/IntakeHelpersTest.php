<?php

use App\Enums\PermitType;
use App\Enums\Role;
use App\Models\FeeSchedule;
use App\Models\Vehicle;
use App\Models\VehicleType;

/*
 * What the office form asks while it is filled in: what each vehicle type
 * of a letter costs, and the vehicles an organization on record already has.
 */

beforeEach(function () {
    $this->registrar = staffUser(Role::Registrar);
});

it('prices each vehicle type of a letter for its days, as approval will', function () {
    $priced = VehicleType::factory()->create();
    $unpriced = VehicleType::factory()->create();
    FeeSchedule::factory()->forType(PermitType::FullDay)->forVehicleType($priced)->amount(75_000)->create();

    $this->actingAs($this->registrar)
        ->getJson(route('admin.permits.fee-estimate', [
            'permit_type' => PermitType::FullDay->value,
            'days' => 10,
            'types' => [$priced->id, $unpriced->id, $priced->id],
        ]))
        ->assertOk()
        ->assertJsonPath("amounts.{$priced->id}", 75_000)
        ->assertJsonPath("amounts.{$unpriced->id}", null);
});

it('lists the vehicles an organization on record already has', function () {
    $applicant = applicantWithOrganization();
    $vehicle = Vehicle::factory()->create(['organization_id' => $applicant->organization_id]);
    Vehicle::factory()->create();

    $this->actingAs($this->registrar)
        ->getJson(route('admin.permits.organization-vehicles', $applicant))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.plate', $vehicle->plate_normalized)
        ->assertJsonPath('data.0.vehicle_type_id', $vehicle->vehicle_type_id);
});

it('answers only those who register requests', function () {
    $applicant = applicantWithOrganization();
    $cashier = staffUser(Role::Cashier);

    $this->actingAs($cashier)
        ->getJson(route('admin.permits.organization-vehicles', $applicant))
        ->assertForbidden();

    $this->actingAs($cashier)
        ->getJson(route('admin.permits.fee-estimate', ['permit_type' => 'full_day', 'days' => 3, 'types' => [1]]))
        ->assertForbidden();

    // Staff are not organizations on record.
    $this->actingAs($this->registrar)
        ->getJson(route('admin.permits.organization-vehicles', $cashier))
        ->assertNotFound();
});
