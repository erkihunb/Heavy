import { useMemo, useState } from 'react';
import {
    actionLinkClassName,
    badgeClassName,
    cx,
    inputClassName,
} from '@/shell/Components/ui';
import { CRITICAL_PERMISSIONS } from '@/shell/lib/permissionGroups';

/** One catalogue permission, with its label and description already in the page's language. */
export type PermissionEntry = {
    name: string;
    label: string;
    description: string | null;
};

export type RoleScope = 'branch' | 'global';

/** Branch-scoped vs global role, as two cards so the difference is read before it is chosen. */
export function RoleScopeField({
    value,
    onChange,
    canManageGlobal,
    error,
    t,
}: {
    value: RoleScope;
    onChange: (scope: RoleScope) => void;
    canManageGlobal: boolean;
    error?: string;
    t: (key: string) => string;
}) {
    const options: {
        value: RoleScope;
        label: string;
        help: string;
        disabled: boolean;
    }[] = [
        {
            value: 'branch',
            label: t('rbac.roles.branchRole'),
            help: t('rbac.roles.branchRoleHelp'),
            disabled: false,
        },
        {
            value: 'global',
            label: t('rbac.roles.globalRole'),
            help: t('rbac.roles.globalRoleHelp'),
            disabled: !canManageGlobal,
        },
    ];

    return (
        <fieldset>
            <legend className="block text-xs font-medium text-(--app-muted-foreground)">
                {t('rbac.roles.scopeType')}
            </legend>
            <div className="mt-1 grid gap-2 sm:grid-cols-2">
                {options.map((o) => (
                    <label
                        key={o.value}
                        aria-disabled={o.disabled}
                        className={cx(
                            'flex cursor-pointer items-start gap-2.5 rounded-card border p-3 transition-colors aria-disabled:cursor-not-allowed aria-disabled:opacity-60',
                            value === o.value
                                ? 'border-primary bg-primary/5'
                                : 'border-(--app-border) hover:bg-(--app-surface-muted)',
                        )}
                    >
                        <input
                            type="radio"
                            name="scope_type"
                            value={o.value}
                            checked={value === o.value}
                            disabled={o.disabled}
                            onChange={() => onChange(o.value)}
                            className="mt-0.5 h-4 w-4 shrink-0 border-(--app-border-strong) text-primary focus:ring-primary"
                        />
                        <span className="min-w-0">
                            <span className="block text-sm font-semibold text-(--app-foreground)">
                                {o.label}
                            </span>
                            <span className="mt-0.5 block text-xs text-(--app-muted-foreground)">
                                {o.help}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            {error && (
                <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}
        </fieldset>
    );
}

/**
 * The role editor's permission checklist: every permission in one list, in
 * catalogue order, with a search box and select-all for what is shown.
 * Permissions are not put into categories.
 */
export default function PermissionPicker({
    permissions,
    selected,
    onChange,
    disabled,
    t,
}: {
    permissions: PermissionEntry[];
    selected: string[];
    onChange: (names: string[]) => void;
    disabled: boolean;
    t: (key: string) => string;
}) {
    const [search, setSearch] = useState('');
    const selectedSet = useMemo(() => new Set(selected), [selected]);

    function set(names: string[], on: boolean) {
        if (disabled) return;
        const next = new Set(selected);
        names.forEach((n) => (on ? next.add(n) : next.delete(n)));
        onChange([...next]);
    }

    const query = search.trim().toLowerCase();
    const visible = query
        ? permissions.filter((p) =>
              [p.name, p.label, p.description].some((v) =>
                  (v ?? '').toLowerCase().includes(query),
              ),
          )
        : permissions;
    const visibleNames = visible.map((p) => p.name);
    const allSelected =
        visibleNames.length > 0 &&
        visibleNames.every((n) => selectedSet.has(n));

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <span
                    className={badgeClassName(
                        selected.length > 0 ? 'success' : 'neutral',
                        'tabular-nums',
                    )}
                >
                    {selected.length}/{permissions.length}
                </span>
                <div className="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        disabled={disabled || visibleNames.length === 0}
                        onClick={() => set(visibleNames, !allSelected)}
                        className={actionLinkClassName()}
                    >
                        {allSelected
                            ? t('rbac.permissions.clearAll')
                            : t('rbac.permissions.selectAll')}
                    </button>
                    <input
                        type="search"
                        aria-label={t('rbac.permissions.search')}
                        placeholder={t('rbac.permissions.search')}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className={cx(inputClassName, 'w-full sm:w-64')}
                    />
                </div>
            </div>

            {visible.length === 0 ? (
                <p className="text-sm text-(--app-muted-foreground)">
                    {t('rbac.permissions.noneFound')}
                </p>
            ) : (
                <div className="grid gap-1.5 rounded-card border border-(--app-border) p-4 sm:grid-cols-2 xl:grid-cols-3">
                    {visible.map((perm) => (
                        <label
                            key={perm.name}
                            className="flex items-start gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-(--app-surface-muted) aria-disabled:cursor-not-allowed aria-disabled:opacity-60 aria-disabled:hover:bg-transparent"
                            aria-disabled={disabled}
                        >
                            <input
                                type="checkbox"
                                className="mt-0.5 h-4 w-4 shrink-0 rounded-sm border-(--app-border-strong) text-primary focus:ring-primary disabled:cursor-not-allowed"
                                checked={selectedSet.has(perm.name)}
                                disabled={disabled}
                                onChange={() =>
                                    set(
                                        [perm.name],
                                        !selectedSet.has(perm.name),
                                    )
                                }
                            />
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-1">
                                    <span className="font-mono text-xs break-all text-(--app-foreground)">
                                        {perm.name}
                                    </span>
                                    {CRITICAL_PERMISSIONS.has(perm.name) && (
                                        <span
                                            title={t(
                                                'rbac.permissions.criticalWarning',
                                            )}
                                            className={badgeClassName('danger')}
                                        >
                                            {t('rbac.permissions.critical')}
                                        </span>
                                    )}
                                </div>
                                {perm.label !== perm.name && (
                                    <p className="text-xs font-medium text-(--app-foreground)">
                                        {perm.label}
                                    </p>
                                )}
                                {perm.description && (
                                    <p className="line-clamp-2 text-xs text-(--app-muted-foreground)">
                                        {perm.description}
                                    </p>
                                )}
                            </div>
                        </label>
                    ))}
                </div>
            )}
        </div>
    );
}
