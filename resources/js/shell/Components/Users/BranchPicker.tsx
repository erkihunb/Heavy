import { useState } from 'react';
import { SearchInput, cx } from '@/shell/Components/ui';
import { useLocale } from '@/shell/hooks/useLocale';

/**
 * A place a branch-scoped role can apply: a branch, or the head office
 * (key "head-office": applications filed without a branch).
 */
export type PickableBranch = { key: string; code: string | null; name: string };

export const HEAD_OFFICE = 'head-office';

/**
 * The branches a user is scoped to, as a searchable checklist (EUISIS
 * OrganizationPicker). The search box shows from eight branches up.
 */
export default function BranchPicker({
    branches,
    selected,
    onChange,
    error,
    invalid,
}: {
    branches: PickableBranch[];
    selected: string[];
    onChange: (keys: string[]) => void;
    error?: string;
    invalid?: boolean;
}) {
    const { t } = useLocale();
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();
    const shown = branches.filter(
        (branch) =>
            !needle ||
            [branch.name, branch.code].some((value) =>
                value?.toLowerCase().includes(needle),
            ),
    );
    const toggle = (key: string) =>
        onChange(
            selected.includes(key)
                ? selected.filter((value) => value !== key)
                : [...selected, key],
        );

    if (branches.length === 0) {
        return (
            <p className="rounded-control border border-dashed border-(--app-border) px-3 py-4 text-center text-sm text-(--app-muted-foreground)">
                {t('rbac.scopes.noBranches')}
            </p>
        );
    }

    return (
        <div className="space-y-2">
            {branches.length >= 8 && (
                <SearchInput
                    value={query}
                    onChange={setQuery}
                    onClear={() => setQuery('')}
                    placeholder={t('rbac.scopes.searchBranches')}
                    label={t('rbac.scopes.searchBranches')}
                />
            )}
            <div
                className={cx(
                    'max-h-64 overflow-y-auto rounded-control border',
                    invalid ? 'border-red-500' : 'border-(--app-border)',
                )}
            >
                {shown.length === 0 ? (
                    <p className="px-3 py-4 text-center text-sm text-(--app-muted-foreground)">
                        {t('rbac.scopes.noBranchesMatch')}
                    </p>
                ) : (
                    <ul className="divide-y divide-(--app-border)">
                        {shown.map((branch) => (
                            <li key={branch.key}>
                                <label className="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-(--app-surface-muted)">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded-sm border-(--app-border-strong) text-primary focus:ring-primary"
                                        checked={selected.includes(branch.key)}
                                        onChange={() => toggle(branch.key)}
                                    />
                                    <span className="min-w-0 flex-1 truncate text-sm text-(--app-foreground)">
                                        {branch.name}
                                    </span>
                                    {branch.code && (
                                        <span className="shrink-0 font-mono text-xs text-(--app-muted-foreground)">
                                            {branch.code}
                                        </span>
                                    )}
                                </label>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <p
                className="text-xs text-(--app-muted-foreground)"
                aria-live="polite"
            >
                {t('rbac.scopes.selectedCount').replace(
                    ':count',
                    String(selected.length),
                )}
            </p>
            {error && (
                <p className="text-xs text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}
        </div>
    );
}
