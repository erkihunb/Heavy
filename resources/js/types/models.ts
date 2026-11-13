import type { DualDate } from '@/types/calendar';
import type {
    PaymentMethod,
    PaymentStatus,
    PermitStatus,
    PermitType,
    Recommendation,
    VehicleRole,
} from '@/types/enums';

/** An amount of money as App\Support\Money::present() sends it. */
export type Money = {
    /** Santim (1 ETB = 100 santim). */
    amount_cents: number;
    currency: string;
    /** E.g. "ETB 1,500.00". */
    formatted: string;
};

/** Mirrors App\Http\Resources\PaymentResource. */
export type Payment = {
    /** ULID. */
    id: string;
    permit_id: string;
    method: PaymentMethod;
    receipt_number: string;
    amount: Money;
    paid_at: DualDate;
    status: PaymentStatus;
    /** The cashier's note: always for cashiers, for applicants only on a rejection. */
    notes: string | null;
    has_receipt_file: boolean;
    submitted_at: DualDate | null;
    decided_at: DualDate | null;
    permit?: Permit;
    can: { verify: boolean; reject: boolean };
    /** Cashiers only. */
    submitted_by?: string | null;
    /** Cashiers only. */
    decided_by?: string | null;
};

/** Mirrors App\Http\Resources\FeeScheduleResource. */
export type FeeSchedule = {
    id: number;
    permit_type: PermitType;
    /** Absent (null) when the fee applies to every vehicle type. */
    vehicle_type: VehicleType | null;
    vehicle_type_id: number | null;
    min_days: number;
    max_days: number;
    amount: Money;
    effective_from: DualDate;
    effective_to: DualDate | null;
    is_current: boolean;
};

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
    /** What the vehicle is, in words, when its type is "other". */
    type_detail: string | null;
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
    startReview: boolean;
    recommend: boolean;
    requestChanges: boolean;
    approve: boolean;
    reject: boolean;
    revoke: boolean;
    reissue: boolean;
    /** Cashier: record a payment taken at the desk or checked with the bank. */
    recordPayment: boolean;
    /** Issuer: issue and print a paid permit. */
    issue: boolean;
    /** Head office: comment on the application. */
    comment: boolean;
};

/** A head-office comment on an application (staff only). */
export type PermitComment = {
    id: number;
    body: string;
    author: string;
    at: DualDate;
    /** HH:MM, Addis Ababa time. */
    time: string;
};

/** Mirrors App\Http\Resources\PermitResource. */
/** One PDF per language; the permit is printed separately in each. */
export type PermitDocumentUrls = {
    am: string;
    en: string;
};

export type Permit = {
    /** ULID. */
    id: string;
    permit_number: string | null;
    /** The branch the application was made at; null for the head office. */
    branch?: { id: number; code: string; name: string } | null;
    status: PermitStatus;
    /** The status as of now: issued permits become active, then expired, by date. */
    effective_status: PermitStatus;
    permit_type: PermitType;
    reason: string;
    valid_from: DualDate;
    valid_to: DualDate;
    duration_days: number;
    /** The hours of the day it lets the vehicles move (Addis Ababa time); null for any time of day. */
    hours: { from: string; to: string; label: string } | null;
    /** Copied from the fee schedule at approval; null until then. */
    fee: Money | null;
    payments?: Payment[];
    vehicles?: PermitVehicle[];
    /** Organization name in the current interface language. */
    organization?: string;
    submitted_at: DualDate | null;
    transitions?: PermitTransition[];
    /** Temporary signed links to the Amharic and English PDFs; null while they are being prepared or not allowed. */
    document_urls: PermitDocumentUrls | null;
    /** Issued, but the PDF is still being rendered. */
    document_pending: boolean;
    can: PermitAbilities;
    /** The letter the request came in with; office staff only. */
    letter?: {
        number: string;
        date: DualDate | null;
        url: string | null;
    } | null;
    /** Staff viewers only. */
    applicant?: string;
    /** Staff viewers only: the head office's comments, oldest first. */
    comments?: PermitComment[];
    /** Staff viewers only: who issued and printed it. */
    issued_by?: string | null;
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
