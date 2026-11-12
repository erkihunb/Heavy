/**
 * Labels for the permission catalogue (App\Support\Rbac\PermissionCatalog):
 * modules (Administration, Permit processing, …) hold groups (users, roles,
 * permits, …), and each group holds permissions named `group.action`.
 * Labels come from lang/{en,am}/rbac.php; a missing label falls back to the
 * catalogue's own name, so nothing renders blank.
 */

/** A permission module as the role and permission screens show it. */
export type PermissionModule = { key: string; name: string; groups: string[] };

/** The display label of a permission group (rbac.groups.<group>). */
export function permissionGroupLabel(
    t: (key: string) => string,
    group: string | null | undefined,
): string {
    if (!group) {
        return '';
    }
    const key = `rbac.groups.${group}`;
    const label = t(key);

    return label && label !== key ? label : group;
}

/** The display label of a permission module (rbac.modules.<key>), falling back to its catalogue name. */
export function permissionModuleLabel(
    t: (key: string) => string,
    key: string,
    fallback?: string,
): string {
    const translationKey = `rbac.modules.${key}`;
    const label = t(translationKey);

    return label && label !== translationKey ? label : (fallback ?? key);
}

/**
 * Permissions that hand out power over other accounts: granting them is
 * flagged in the picker so it is not done in passing.
 */
export const CRITICAL_PERMISSIONS: ReadonlySet<string> = new Set([
    'users.assign-roles',
    'users.deactivate',
    'roles.create',
    'roles.update',
    'roles.delete',
    'settings.manage',
]);
