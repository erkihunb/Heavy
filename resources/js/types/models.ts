import type { DualDate } from '@/types/calendar';

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
