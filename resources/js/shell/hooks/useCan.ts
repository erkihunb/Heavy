import { usePage } from '@inertiajs/react';

/**
 * Permission checks for showing or hiding navigation (as in the source kit). Only a
 * convenience: every route is authorised on the server by its Policy.
 * Admins hold every staff permission in the database (docs/DECISIONS.md), so
 * no role is a wildcard here; `isSuperAdmin` stays false.
 */
export function useCan() {
    const { auth } = usePage().props;
    const permissions = auth.permissions ?? [];
    const isSuperAdmin = auth.isSuperAdmin === true;

    const can = (permission: string): boolean =>
        isSuperAdmin || permissions.includes(permission);

    return { can, isSuperAdmin };
}
