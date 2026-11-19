<?php

namespace App\Enums;

/**
 * Permission names stored by spatie/laravel-permission. Policies check these;
 * the role → permission mapping lives in {@see Role::permissions()}.
 */
enum Permission: string
{
    // Everyone who should see it: the home screen with its figures and what needs attention
    case ViewDashboard = 'dashboard.view';

    // Applicant (always scoped to the user's own organization by the Policy)
    case ApplyForPermits = 'permits.apply';

    // Reviewer
    case ViewReviewQueue = 'permits.view-queue';
    // Seeing permits once issued (issued, in force, expired, revoked): lists, pages, certificates.
    case ViewIssuedPermits = 'permits.view-issued';
    case ReviewPermits = 'permits.review';

    // Approver
    case ApprovePermits = 'permits.approve';
    case RevokePermits = 'permits.revoke';

    // Cashier / Finance: verify receipts and record payments taken at the office
    case VerifyPayments = 'payments.verify';
    // The record of payments taken, read only
    case ViewPayments = 'payments.view';

    // Issuer: check a paid application, issue the permit and print it
    case IssuePermits = 'permits.issue';

    // Head office: leave comments on any branch's applications
    case CommentOnPermits = 'permits.comment';

    // Traffic Officer
    case ScanPermits = 'verification.scan';

    // Give the Authority's own vehicles their permits directly: no review, approval or payment
    case IssueInstitutionPermits = 'institution-permits.issue';
    // See the permits given to the Authority's own vehicles; revoke one
    case ViewInstitutionPermits = 'institution-permits.view';
    case RevokeInstitutionPermits = 'institution-permits.revoke';
    // The register of the Authority's own vehicles: see, add, change, remove
    case ViewInstitutionVehicles = 'institution-vehicles.view';
    case CreateInstitutionVehicles = 'institution-vehicles.create';
    case UpdateInstitutionVehicles = 'institution-vehicles.update';
    case DeleteInstitutionVehicles = 'institution-vehicles.delete';

    // Registrar: register applicants who come to the office in person
    case RegisterApplicants = 'applicants.register';

    // Registrar: correct a request registered at the office while it is a draft or
    // returned for changes, its vehicles' plate, type and owner included
    case UpdateRegisteredPermits = 'permits.update-registered';

    // Registrar: delete (withdraw) such a request; it stays in the record as withdrawn
    case DeleteRegisteredPermits = 'permits.delete-registered';

    // The organizations requests are registered for, and their vehicles: see, correct, delete.
    // (They are added with a request, by applicants.register.)
    case ViewOrganizations = 'organizations.view';
    case UpdateOrganizations = 'organizations.update';
    case DeleteOrganizations = 'organizations.delete';
    case UpdateVehicles = 'vehicles.update';
    case DeleteVehicles = 'vehicles.delete';

    // Approver and Cashier: the reporting dashboard and exports
    case ViewReports = 'reports.view';

    // Admin: users, roles and permissions (App\Support\Rbac\PermissionCatalog)
    case ViewUsers = 'users.view';
    case CreateUsers = 'users.create';
    case UpdateUsers = 'users.update';
    case DeactivateUsers = 'users.deactivate';
    // Set a temporary password, send a reset link, or turn off a lost two-factor device.
    case ResetPasswords = 'users.reset-passwords';
    case AssignRoles = 'users.assign-roles';
    case ViewRoles = 'roles.view';
    case CreateRoles = 'roles.create';
    case UpdateRoles = 'roles.update';
    case DeleteRoles = 'roles.delete';
    // Global roles see every branch; the administrator role is changed only with this.
    case ManageGlobalRoles = 'roles.manage-global';
    case ViewPermissions = 'permissions.view';

    // Admin: configuration, each part seen, added, changed and removed by its own permission
    case ViewVehicleTypes = 'vehicle-types.view';
    case CreateVehicleTypes = 'vehicle-types.create';
    case UpdateVehicleTypes = 'vehicle-types.update';
    case DeleteVehicleTypes = 'vehicle-types.delete';
    case ViewFeeSchedules = 'fee-schedules.view';
    case CreateFeeSchedules = 'fee-schedules.create';
    case UpdateFeeSchedules = 'fee-schedules.update';
    case DeleteFeeSchedules = 'fee-schedules.delete';
    case ViewBranches = 'branches.view';
    case CreateBranches = 'branches.create';
    case UpdateBranches = 'branches.update';
    case DeleteBranches = 'branches.delete';
    case ViewSettings = 'settings.view';
    case UpdateSettings = 'settings.update';
    case ViewAuditLog = 'audit-log.view';
}
