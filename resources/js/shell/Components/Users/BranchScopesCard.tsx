import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, EmptyState, badgeClassName } from '@/shell/Components/ui';
import BranchPicker from '@/shell/Components/Users/BranchPicker';
import type { PickableBranch } from '@/shell/Components/Users/BranchPicker';
import { useConfirm } from '@/shell/hooks/useConfirm';
import { useLocale } from '@/shell/hooks/useLocale';

/** One scope row: the branch it names (null for the head office) and where to remove it. */
export type BranchScopeRow = {
    id: number;
    key: string;
    name: string;
    code: string | null;
    active: boolean;
    destroyUrl: string;
};

/**
 * Where a user's branch-scoped roles apply (EUISIS OrganizationScopesCard).
 * Fail closed: a user whose roles are branch-scoped and who has no row sees no
 * application. Global roles ignore the rows, which is said on the card.
 */
export default function BranchScopesCard({
    scopes,
    branches,
    storeUrl,
    canManage,
    allGlobal,
}: {
    scopes: BranchScopeRow[];
    branches: PickableBranch[];
    storeUrl: string;
    canManage: boolean;
    /** Every role the user holds is global, so the rows change nothing. */
    allGlobal: boolean;
}) {
    const { t } = useLocale();
    const { confirm } = useConfirm();
    const [adding, setAdding] = useState(false);
    const [picked, setPicked] = useState<string[]>([]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | undefined>();

    const assigned = new Set(scopes.map((scope) => scope.key));
    const available = branches.filter((branch) => !assigned.has(branch.key));

    function save() {
        router.post(
            storeUrl,
            { branches: picked },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onError: (errors) =>
                    setError(errors.branches ?? Object.values(errors)[0]),
                onSuccess: () => {
                    setPicked([]);
                    setAdding(false);
                    setError(undefined);
                },
            },
        );
    }

    async function remove(scope: BranchScopeRow) {
        const { confirmed } = await confirm({
            title: t('rbac.scopes.confirmRemove'),
            description: t('rbac.scopes.confirmRemoveBody').replace(
                ':branch',
                scope.name,
            ),
            confirmLabel: t('rbac.scopes.remove'),
            variant: 'danger',
        });
        if (confirmed)
            router.delete(scope.destroyUrl, { preserveScroll: true });
    }

    return (
        <aside
            className="space-y-4 rounded-panel border border-(--app-border) bg-(--app-surface) p-5"
            aria-labelledby="branch-scopes-title"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2
                        id="branch-scopes-title"
                        className="text-sm font-semibold text-(--app-foreground)"
                    >
                        {t('rbac.scopes.title')}
                    </h2>
                    <p className="mt-0.5 text-xs text-(--app-muted-foreground)">
                        {t('rbac.scopes.help')}
                    </p>
                </div>
                {canManage && !adding && available.length > 0 && (
                    <Button
                        size="xs"
                        variant="outline"
                        onClick={() => setAdding(true)}
                    >
                        {t('rbac.scopes.add')}
                    </Button>
                )}
            </div>

            {allGlobal && (
                <p className="rounded-control border border-(--app-border) bg-(--app-surface-muted) px-3 py-2 text-xs text-(--app-muted-foreground)">
                    {t('rbac.scopes.globalNotice')}
                </p>
            )}

            {scopes.length === 0 ? (
                <EmptyState
                    icon={null}
                    className="px-2 py-6"
                    title={t('rbac.scopes.none')}
                    description={
                        allGlobal ? undefined : t('rbac.scopes.noneFailClosed')
                    }
                />
            ) : (
                <ul className="divide-y divide-(--app-border) rounded-control border border-(--app-border)">
                    {scopes.map((scope) => (
                        <li
                            key={scope.id}
                            className="flex items-center gap-3 px-3 py-2.5"
                        >
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium text-(--app-foreground)">
                                    {scope.name}
                                </span>
                                {scope.code && (
                                    <span className="font-mono text-xs text-(--app-muted-foreground)">
                                        {scope.code}
                                    </span>
                                )}
                            </span>
                            {!scope.active && (
                                <span className={badgeClassName('warning')}>
                                    {t('rbac.scopes.inactiveBranch')}
                                </span>
                            )}
                            {canManage && (
                                <button
                                    type="button"
                                    onClick={() => void remove(scope)}
                                    className="text-xs font-medium text-red-700 hover:underline dark:text-red-400"
                                >
                                    {t('rbac.scopes.remove')}
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {adding && (
                <div className="space-y-3 border-t border-(--app-border) pt-4">
                    <p className="text-xs font-medium text-(--app-muted-foreground)">
                        {t('rbac.scopes.selectBranches')}
                    </p>
                    <BranchPicker
                        branches={available}
                        selected={picked}
                        onChange={setPicked}
                        error={error}
                        invalid={!!error}
                    />
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setAdding(false);
                                setPicked([]);
                                setError(undefined);
                            }}
                        >
                            {t('shell.common.cancel')}
                        </Button>
                        <Button
                            size="sm"
                            loading={saving}
                            disabled={picked.length === 0}
                            onClick={save}
                        >
                            {t('rbac.scopes.save')}
                        </Button>
                    </div>
                </div>
            )}
        </aside>
    );
}
