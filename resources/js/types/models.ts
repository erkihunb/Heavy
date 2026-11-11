import type { DualDate } from '@/types/calendar';
import type {
    PermitStatus,
    PermitType,
    Recommendation,
    VehicleRole,
} from '@/types/enums';

/** Mirrors App\Http\Resources\OrganizationResource. */
export type Organization = {
    id: number;
    /** Name in the current interface language. */
    name: string;
    name_am: string;
    name_en: string | null;
    tin: string | null;
    phone: string;
    email: string;
    address: string;
};

/** Mirrors App\Http\Resources\VehicleTypeResource. */
export type VehicleType = {
    id: number;
    code: string;
    /** Name in the current interface language. */
    name: string;
};

/** Mirrors App\Http\Resources\VehicleResource. */
export type Vehicle = {
    /** ULID. */
    id: string;
    /** Normalised plate, e.g. "አአ-03-A45301". */
    plate: string;
    region_code: string;
    owner_name: string;
    vehicle_type: VehicleType;
    registered_at: DualDate;
};

/** A plate region prefix (App\Enums\PlateRegion) with its Latin spelling. */
export type PlateRegionOption = {
    value: string;
    latin: string;
};

/** A Laravel paginated resource collection. */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

/** Mirrors App\Http\Resources\PermitVehicleResource. */
export type PermitVehicle = {
    /** ULID of the vehicle. */
    id: string;
    plate: string;
    role: VehicleRole;
    owner_name: string;
    vehicle_type?: VehicleType;
};

/** Mirrors App\Http\Resources\PermitTransitionResource. */
export type PermitTransition = {
    from: PermitStatus | null;
    to: PermitStatus;
    note: string | null;
    at: DualDate;
    /** Time of day in the display timezone, "HH:mm". */
    time: string;
    /** Staff viewers only. */
    actor?: string | null;
};

/** What the signed-in user may do with a permit right now (buttons only). */
export type PermitAbilities = {
    update: boolean;
    submit: boolean;
    withdraw: boolean;
    startReview: boolean;
    recommend: boolean;
    requestChanges: boolean;
    approve: boolean;
    reject: boolean;
    revoke: boolean;
};

/** Mirrors App\Http\Resources\PermitResource. */
export type Permit = {
    /** ULID. */
    id: string;
    permit_number: string | null;
    status: PermitStatus;
    /** The status as of now: issued permits become active, then expired, by date. */
    effective_status: PermitStatus;
    permit_type: PermitType;
    reason: string;
    valid_from: DualDate;
    valid_to: DualDate;
    duration_days: number;
    vehicles?: PermitVehicle[];
    /** Organization name in the current interface language. */
    organization?: string;
    submitted_at: DualDate | null;
    transitions?: PermitTransition[];
    can: PermitAbilities;
    /** Staff viewers only. */
    applicant?: string;
    /** Staff viewers only: whole hours waiting for the Authority. */
    waiting_hours?: number | null;
    /** Staff viewers only. */
    recommendation?: {
        decision: Recommendation;
        note: string | null;
        by?: string | null;
        at: DualDate | null;
    } | null;
};

/** Mirrors App\Http\Resources\VehiclePermitHistoryResource. */
export type VehiclePermitHistory = {
    id: string;
    permit_number: string | null;
    status: PermitStatus;
    effective_status: PermitStatus;
    permit_type: PermitType;
    valid_from: DualDate;
    valid_to: DualDate;
    /** Reserves the vehicle for days the application under review also asks for. */
    conflicts: boolean;
};

/** One vehicle of an application with its other permits (staff detail page). */
export type VehicleHistory = {
    vehicle_id: string;
    plate: string;
    has_conflict: boolean;
    permits: VehiclePermitHistory[];
};
