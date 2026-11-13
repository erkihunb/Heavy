import { useMemo, useState } from 'react';
import { SearchInput, cx } from '@/shell/Components/ui';
import { useLocale } from '@/shell/hooks/useLocale';

/** A role an administrator may give: `name` is its code, `label` its name in the page's language. */
export type PickableRole = {
    id: number;
    name: string;
    label: string;
    scope: 'branch' | 'global';
};

/**
 * The roles to give a user: a search box, a "selected only" switch and every
 * role in one list. Roles are not put into categories: what a role does is
 * decided by the permissions it holds, never by its name.
 */
export default function RolePicker({
    roles,
    selected,
    onToggle,
    onClear,
    error,
}: {
    roles: PickableRole[];
    selected: number[];
    onToggle: (id: number) => void;
    onClear: () => void;
    error?: string;
}) {
    const { t } = useLocale();
    const [query, setQuery] = useState('');
    const [selectedOnly, setSelectedOnly] = useState(false);
    const needle = query.trim().toLowerCase();

    const shown = useMemo(
        () =>
            roles.filter(
                (role) =>
                    (!needle ||
                        role.name.toLowerCase().includes(needle) ||
                        role.label.toLowerCase().includes(needle)) &&
                    (!selectedOnly || selected.includes(role.id)),
            ),
        [roles, needle, selectedOnly, selected],
    );

    if (roles.length === 0) {
        return (
            <p className="rounded-control border border-dashed border-(--app-border) px-3 py-4 text-center text-sm text-(--app-muted-foreground)">
                {t('rbac.users.noRoles')}
            </p>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <SearchInput
                    value={query}
                    onChange={setQuery}
                    onClear={() => setQuery('')}
                    placeholder={t('rbac.users.searchRoles')}
                    label={t('rbac.users.searchRoles')}
                    className="min-w-56 flex-1"
                />
                <label className="inline-flex cursor-pointer items-center gap-2 rounded-control border border-(--app-border) px-3 py-2 text-sm text-(--app-foreground) hover:bg-(--app-surface-muted)">
                    <input
                        type="checkbox"
                        className="h-4 w-4 rounded-sm border-(--app-border-strong) text-primary focus:ring-primary"
                        checked={selectedOnly}
                        onChange={(event) =>
                            setSelectedOnly(event.target.checked)
                        }
                    />
                    {t('rbac.users.selectedOnly')}
                </label>
            </div>

            <div
                className="flex items-center justify-between text-xs text-(--app-muted-foreground)"
                aria-live="polite"
            >
                <span>
                    {t('rbac.users.selectedCount').replace(
                        ':count',
                        String(selected.length),
                    )}
                </span>
                {selected.length > 0 && (
                    <button
                        type="button"
                        onClick={onClear}
                        className="font-medium text-primary hover:underline"
                    >
                        {t('rbac.users.clearSelection')}
                    </button>
                )}
            </div>

            {shown.length === 0 ? (
                <p className="rounded-control border border-dashed border-(--app-border) px-3 py-4 text-center text-sm text-(--app-muted-foreground)">
                    {t('rbac.users.noRolesMatch')}
                </p>
            ) : (
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    {shown.map((role) => {
                        const checked = selected.includes(role.id);
                        return (
                            <label
                                key={role.id}
                                className={cx(
                                    'flex cursor-pointer items-start gap-2.5 rounded-control border px-3 py-2.5 transition-colors',
                                    checked
                                        ? 'border-primary bg-primary/5'
                                        : 'border-(--app-border) hover:bg-(--app-surface-muted)',
                                )}
                            >
                                <input
                                    type="checkbox"
                                    className="mt-0.5 h-4 w-4 shrink-0 rounded-sm border-(--app-border-strong) text-primary focus:ring-primary"
                                    checked={checked}
                                    onChange={() => onToggle(role.id)}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium text-(--app-foreground)">
                                        {role.label}
                                    </span>
                                    <span className="mt-0.5 block text-xs text-(--app-muted-foreground)">
                                        {role.scope === 'branch'
                                            ? t('rbac.users.branchRole')
                                            : t('rbac.users.globalRole')}
                                    </span>
                                </span>
                            </label>
                        );
                    })}
                </div>
            )}
            {error && (
                <p className="text-xs text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}
        </div>
    );
}
