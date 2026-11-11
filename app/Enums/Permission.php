<?php

namespace App\Enums;

/**
 * Permission names stored by spatie/laravel-permission. Policies check these;
 * the role → permission mapping lives in {@see Role::permissions()}.
 */
enum Permission: string
{
    // Applicant (always scoped to the user's own organization by the Policy)
    case ManageOwnOrganization = 'organization.manage-own';
    case ManageOwnVehicles = 'vehicles.manage-own';
    case ApplyForPermits = 'permits.apply';
    case SubmitPayments = 'payments.submit';
    case DownloadOwnPermits = 'permits.download-own';

    // Reviewer
    case ViewReviewQueue = 'permits.view-queue';
    case ReviewPermits = 'permits.review';

    // Approver
    case ApprovePermits = 'permits.approve';
    case RevokePermits = 'permits.revoke';

    // Cashier / Finance
    case VerifyPayments = 'payments.verify';

    // Traffic Officer
    case ScanPermits = 'verification.scan';

    // Registrar: register applicants who come to the office in person
    case RegisterApplicants = 'applicants.register';

    // Approver and Cashier: the reporting dashboard and exports
    case ViewReports = 'reports.view';

    // Admin
    case ManageUsers = 'users.manage';
    case ManageVehicleTypes = 'vehicle-types.manage';
    case ManageFeeSchedules = 'fee-schedules.manage';
    case ManageSettings = 'settings.manage';
    case ViewAuditLog = 'audit-log.view';
}
