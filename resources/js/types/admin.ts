import type { DualDate } from '@/types';
import type { Role } from '@/types/enums';

/** Mirrors App\Http\Controllers\Admin\UserController::present(). */
export type AdminUser = {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    two_factor: boolean;
    created_at: DualDate | null;
    can_change_role: boolean;
    role_locked_reason: string | null;
};

/** Mirrors App\Http\Controllers\Admin\VehicleTypeController::present(). */
export type AdminVehicleType = {
    id: number;
    code: string;
    name_am: string;
    name_en: string;
    is_active: boolean;
    sort_order: number;
};

/** Mirrors App\Http\Controllers\Admin\AuditLogController::index(). */
export type AuditEntry = {
    id: number;
    event: string;
    created_at: DualDate | null;
    created_time: string | null;
    actor: { name: string; email: string } | null;
    subject: {
        type: 'permit' | 'payment' | 'user' | 'vehicle_type' | 'fee_schedule';
        label: string;
        url: string | null;
    } | null;
    old: Record<string, unknown>;
    new: Record<string, unknown>;
    context: Record<string, unknown>;
};
