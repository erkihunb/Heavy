<?php

namespace App\Domain\Audit;

/**
 * Everything the audit log records (spec §15).
 */
enum AuditEvent: string
{
    case PermitTransition = 'permit.transition';
    case PermitReissued = 'permit.reissued';
    case PaymentVerified = 'payment.verified';
    case PaymentRejected = 'payment.rejected';
    case FeeScheduleSaved = 'fee_schedule.saved';
    case UserCreated = 'user.created';
    case UserRoleChanged = 'user.role_changed';
    case VehicleTypeCreated = 'vehicle_type.created';
    case VehicleTypeUpdated = 'vehicle_type.updated';
    case SettingsUpdated = 'settings.updated';
}
