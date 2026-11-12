<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
 * Role × action for the Milestone 5 permit abilities: download, reissue,
 * scan and report.
 */

/**
 * A user with the role who belongs to the permit's organization.
 */
function verificationMember(Role $role, Permit $permit): User
{
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => $permit->organization_id])->save();

    return $user;
}

it('lets only traffic officers and Admins use the officer view', function (Role $role) {
    expect(userWithRole($role)->can('scan', Permit::class))
        ->toBe(in_array($role, [Role::TrafficOfficer, Role::Admin], true));
})->with(Role::cases());

it('lets officers and Admins report on issued, expired and revoked permits only', function (Role $role, PermitStatus $status) {
    $permit = Permit::factory()->create(['status' => $status]);
    $allowed = in_array($role, [Role::TrafficOfficer, Role::Admin], true)
        && in_array($status, [PermitStatus::Issued, PermitStatus::Expired, PermitStatus::Revoked], true);

    expect(userWithRole($role)->can('report', $permit))->toBe($allowed);
})->with(Role::cases())->with([PermitStatus::Issued, PermitStatus::Expired, PermitStatus::Revoked, PermitStatus::AwaitingPayment, PermitStatus::Draft]);

it('lets Approvers and Admins reissue', function (Role $role) {
    $permit = Permit::factory()->issued()->create();

    expect(verificationMember($role, $permit)->can('reissue', $permit))
        ->toBe(in_array($role, [Role::Approver, Role::Admin], true));
})->with(Role::cases());

it('lets the organization download its issued or expired permit', function (PermitStatus $status, bool $allowed) {
    $permit = Permit::factory()->create(['status' => $status]);

    expect(verificationMember(Role::Applicant, $permit)->can('download', $permit))->toBe($allowed);
})->with([
    [PermitStatus::Issued, true],
    [PermitStatus::Expired, true],
    [PermitStatus::Revoked, false],
    [PermitStatus::AwaitingPayment, false],
    [PermitStatus::Draft, false],
]);

it('answers not found to another organization', function () {
    $permit = Permit::factory()->issued()->create();

    expect(Gate::forUser(applicantWithOrganization())->inspect('download', $permit)->status())->toBe(404);
});

it('lets staff who can see the permit download it', function (Role $role) {
    $permit = Permit::factory()->issued()->create();

    expect(userWithRole($role)->can('download', $permit))
        ->toBe(in_array($role, [Role::Reviewer, Role::Approver, Role::Cashier, Role::Registrar, Role::Issuer, Role::Supervisor, Role::Admin], true));
})->with(Role::staff());
