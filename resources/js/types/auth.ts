/** Mirrors App\Enums\Role. For navigation and display only — never for authorization. */
export type Role =
    | 'applicant'
    | 'reviewer'
    | 'approver'
    | 'cashier'
    | 'traffic_officer'
    | 'admin';

/** The whitelisted user fields shared by HandleInertiaRequests. */
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    locale: string;
    /** Whether the user belongs to an organization (applicants only). */
    has_organization: boolean;
    roles: Role[];
};

export type Auth = {
    user: User;
    /** True while a staff member has not yet confirmed two-factor authentication. */
    mustEnableTwoFactor: boolean;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
