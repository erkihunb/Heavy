<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;

/*
 * Role × action matrix for the organization profile.
 */

it('lets only applicants without an organization create one', function (Role $role) {
    $user = userWithRole($role);

    expect($user->can('create', Organization::class))->toBe($role === Role::Applicant);
})->with(Role::cases());

it('does not let anyone who already belongs to an organization create another', function (Role $role) {
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => Organization::factory()->create()->id])->save();

    expect($user->can('create', Organization::class))->toBeFalse();
})->with(Role::cases());

it('lets only applicant members view their organization', function (Role $role) {
    $organization = Organization::factory()->create();
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => $organization->id])->save();

    expect($user->can('view', $organization))->toBe($role === Role::Applicant);
})->with(Role::cases());

it('lets only the applicant who owns the organization update it', function (Role $role) {
    $user = userWithRole($role);
    $organization = Organization::factory()->create(['created_by' => $user->id]);
    $user->forceFill(['organization_id' => $organization->id])->save();

    expect($user->can('update', $organization))->toBe($role === Role::Applicant);
})->with(Role::cases());

it('does not let a member who is not the owner update it', function () {
    $organization = Organization::factory()->create();
    $member = userWithRole(Role::Applicant);
    $member->forceFill(['organization_id' => $organization->id])->save();

    expect($member->can('view', $organization))->toBeTrue()
        ->and($member->can('update', $organization))->toBeFalse();
});

it('denies every role access to an organization they do not belong to', function (Role $role) {
    $organization = Organization::factory()->create();
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => Organization::factory()->create()->id])->save();

    expect($user->can('view', $organization))->toBeFalse()
        ->and($user->can('update', $organization))->toBeFalse();
})->with(Role::cases());

it('does not let a former owner who left the organization update it', function () {
    $user = userWithRole(Role::Applicant);
    $organization = Organization::factory()->create(['created_by' => $user->id]);

    expect($user->can('update', $organization))->toBeFalse();
});

it('denies a user without any role', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->forceFill(['organization_id' => $organization->id])->save();

    expect($user->can('create', Organization::class))->toBeFalse()
        ->and($user->can('view', $organization))->toBeFalse()
        ->and($user->can('update', $organization))->toBeFalse();
});
