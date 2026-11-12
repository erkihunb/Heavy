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
    // A receipt the Registrar took with the request, before the decision.
    case PaymentTakenAtIntake = 'payment.taken_at_intake';
    case PermitCommented = 'permit.commented';
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
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
}
