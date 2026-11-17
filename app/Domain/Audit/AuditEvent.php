<?php

namespace App\Domain\Audit;

/**
 * Everything the audit log records (spec §15).
 */
enum AuditEvent: string
{
    case PermitTransition = 'permit.transition';
    case PermitReissued = 'permit.reissued';
    case DocumentRegenerated = 'permit.document_regenerated';
    // Its PDFs printed again with the revoked/expired stamp (by the system).
    case DocumentStamped = 'permit.document_stamped';
    // Its PDF opened for download or printing.
    case DocumentDownloaded = 'permit.document_downloaded';
    case PaymentVerified = 'payment.verified';
    case PaymentRecorded = 'payment.recorded';
    // A receipt the Registrar took with the request, before the decision. No longer
    // recorded; kept so audit-log rows written before that change keep their label.
    case PaymentTakenAtIntake = 'payment.taken_at_intake';
    case PermitCommented = 'permit.commented';
    // A Reviewer's assessment and recommendation to the Approver.
    case PermitReviewed = 'permit.reviewed';
    case PaymentRejected = 'payment.rejected';
    case FeeScheduleSaved = 'fee_schedule.saved';
    case UserCreated = 'user.created';
    case ApplicantRegistered = 'applicant.registered';
    case UserRoleChanged = 'user.role_changed';
    case VehicleTypeCreated = 'vehicle_type.created';
    case VehicleTypeUpdated = 'vehicle_type.updated';
    case BranchCreated = 'branch.created';
    case BranchUpdated = 'branch.updated';
    case UserBranchChanged = 'user.branch_changed';
    case SettingsUpdated = 'settings.updated';
    // Users, roles and branch scopes (EUISIS RBAC).
    case UserUpdated = 'user.updated';
    case UserDeactivated = 'user.deactivated';
    case UserRestored = 'user.restored';
    case UserScopesChanged = 'user.scopes_changed';
    case UserPermissionsChanged = 'user.permissions_changed';
    case UserPasswordReset = 'user.password_reset';
    case UserTwoFactorReset = 'user.two_factor_reset';
    // The Authority's own vehicles (the register; their permits are permit transitions).
    case InstitutionVehicleUpdated = 'institution_vehicle.updated';
    case InstitutionVehicleDeleted = 'institution_vehicle.deleted';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
}
