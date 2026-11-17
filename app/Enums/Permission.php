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
    case ReviewPermits = 'permits.review';

    // Approver
    case ApprovePermits = 'permits.approve';
    case RevokePermits = 'permits.revoke';

    // Cashier / Finance: verify receipts and record payments taken at the office
    case VerifyPayments = 'payments.verify';

    // Issuer: check a paid application, issue the permit and print it
    case IssuePermits = 'permits.issue';

    // Head office: leave comments on any branch's applications
    case CommentOnPermits = 'permits.comment';

    // Traffic Officer
    case ScanPermits = 'verification.scan';

    // Give the Authority's own vehicles their permits directly: no review, approval or payment
    case IssueInstitutionPermits = 'institution-permits.issue';

    // Registrar: register applicants who come to the office in person
    case RegisterApplicants = 'applicants.register';

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

    // Admin: configuration and the audit log
    case ManageVehicleTypes = 'vehicle-types.manage';
    case ManageFeeSchedules = 'fee-schedules.manage';
    case ManageSettings = 'settings.manage';
    case ViewAuditLog = 'audit-log.view';
}
