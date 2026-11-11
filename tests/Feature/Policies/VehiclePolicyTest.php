<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;

/*
 * Role × action matrix for vehicles. Only applicants manage vehicles, and only
 * those of their own organization.
 */

it('lets only applicants with an organization list and register vehicles', function (Role $role) {
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => Organization::factory()->create()->id])->save();

    $expected = $role === Role::Applicant;

    expect($user->can('viewAny', Vehicle::class))->toBe($expected)
        ->and($user->can('create', Vehicle::class))->toBe($expected);
})->with(Role::cases());

it('lets an applicant without an organization browse but not register', function () {
    $user = userWithRole(Role::Applicant);

    expect($user->can('viewAny', Vehicle::class))->toBeTrue()
        ->and($user->can('create', Vehicle::class))->toBeFalse();
});

it('lets only applicants of the same organization view and update a vehicle', function (Role $role) {
    $vehicle = Vehicle::factory()->create();
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => $vehicle->organization_id])->save();

    $expected = $role === Role::Applicant;

    expect($user->can('view', $vehicle))->toBe($expected)
        ->and($user->can('update', $vehicle))->toBe($expected);
})->with(Role::cases());

it('denies every role access to a vehicle of another organization', function (Role $role) {
    $vehicle = Vehicle::factory()->create();
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => Organization::factory()->create()->id])->save();

    expect($user->can('view', $vehicle))->toBeFalse()
        ->and($user->can('update', $vehicle))->toBeFalse();
})->with(Role::cases());

it('denies a user without any role', function () {
    $vehicle = Vehicle::factory()->create();
    $user = User::factory()->create();
    $user->forceFill(['organization_id' => $vehicle->organization_id])->save();

    expect($user->can('viewAny', Vehicle::class))->toBeFalse()
        ->and($user->can('create', Vehicle::class))->toBeFalse()
        ->and($user->can('view', $vehicle))->toBeFalse()
        ->and($user->can('update', $vehicle))->toBeFalse();
});

it('has no policy for deleting vehicles', function () {
    $vehicle = Vehicle::factory()->create();
    $user = userWithRole(Role::Applicant);
    $user->forceFill(['organization_id' => $vehicle->organization_id])->save();

    expect($user->can('delete', $vehicle))->toBeFalse();
});
