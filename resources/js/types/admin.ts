import type { DualDate } from '@/types';
import type { Role } from '@/types/enums';

/** Mirrors App\Http\Controllers\Admin\UserController::present(). */
export type AdminUser = {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    branch: BranchOption | null;
    /** Only staff are placed at a branch. */
    is_staff: boolean;
    two_factor: boolean;
    created_at: DualDate | null;
    can_change_role: boolean;
    role_locked_reason: string | null;
};

/** Mirrors App\Http\Controllers\Admin\UserController::presentBranch(); the name is in the interface language. */
export type BranchOption = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

/** Mirrors App\Http\Controllers\Admin\BranchController::present(). */
export type AdminBranch = {
    id: number;
    code: string;
    name_am: string;
    name_en: string;
    is_active: boolean;
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
        type:
            | 'permit'
            | 'payment'
            | 'user'
            | 'vehicle_type'
            | 'fee_schedule'
            | 'branch'
            | 'vehicle';
        label: string;
        url: string | null;
    } | null;
    /** Each changed field, labelled in the reader's language, old → new. */
    changes: {
        key: string;
        label: string;
        old: unknown;
        new: unknown;
        had_old: boolean;
    }[];
    /** Anything else recorded with the change (section, language, reason…). */
    context: { key: string; label: string; value: unknown }[];
};

/** The applicant a Registrar is working for (App\Http\Controllers\Admin\WalkInController::summary()). */
export type WalkInApplicant = {
    id: number;
    name: string;
    /** Organization name in the current interface language. */
    organization: string;
};
