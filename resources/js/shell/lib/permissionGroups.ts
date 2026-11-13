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
