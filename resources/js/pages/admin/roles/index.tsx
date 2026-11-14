import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { visitWithFilters } from '@/lib/filters';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import EmptyState from '@/shell/Components/EmptyState';
import { Plus } from '@/shell/Components/Icons';
import PageHeader from '@/shell/Components/PageHeader';
import {
    AppActionMenu,
    FilterField,
    FilterPanel,
    Input,
    ListPagination,
    TableCard,
    TableTools,
    buttonClassName,
    cx,
    tableBodyClassName,
    tableCellClassName,
    tableClassName,
    tableHeadClassName,
    tableHeaderCellClassName,
    tableRowClassName,
} from '@/shell/Components/ui';
import type { LaravelPageMeta } from '@/shell/Components/ui';
import { useConfirm } from '@/shell/hooks/useConfirm';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

type Scope = 'branch' | 'global';

type RoleRow = {
    id: number;
    name: string;
    label: string;
    name_am: string | null;
    scope_type: Scope;
    users_count: number;
    permissions_count: number;
    is_protected: boolean;
    is_system: boolean;
    can: { update: boolean; delete: boolean };
    editUrl: string;
    destroyUrl: string;
};

type Filters = { search: string; scope: string };

const SCOPE_DOT: Record<Scope, string> = {
    branch: 'bg-sky-500',
    global: 'bg-purple-500',
};

/** The roles in one list, filtered by scope: roles are not put into categories. */
function RolesIndexPage({
    roles,
    filters,
    scopeCounts,
    can,
    urls,
}: {
    roles: { data: RoleRow[] } & LaravelPageMeta;
    filters: Filters;
    scopeCounts: Record<Scope, number>;
    can: { create: boolean };
    urls: { index: string; create: string };
}) {
    const { t } = useLocale();
    const { confirm } = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const filtersActive = Object.values(filters).some(Boolean);

    function go(next: Partial<Filters>) {
        visitWithFilters(urls.index, { ...filters, search, ...next });
    }

    function resetFilters() {
        setSearch('');
        router.get(urls.index, {}, { preserveScroll: true, replace: true });
    }

    async function destroy(role: RoleRow) {
        const { confirmed } = await confirm({
            title: t('rbac.roles.confirmDeleteTitle').replace(
                ':name',
                role.label,
            ),
            description: t('rbac.roles.confirmDeleteBody'),
            confirmLabel: t('shell.actions.delete'),
            variant: 'danger',
        });
        if (confirmed) router.delete(role.destroyUrl, { preserveScroll: true });
    }

    const scopeTabs: { value: '' | Scope; label: string; count: number }[] = [
        {
            value: '',
            label: t('rbac.roles.allScopes'),
            count: scopeCounts.global + scopeCounts.branch,
        },
        {
            value: 'branch',
            label: t('rbac.roles.branchRole'),
            count: scopeCounts.branch,
        },
        {
            value: 'global',
            label: t('rbac.roles.globalRole'),
            count: scopeCounts.global,
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('rbac.roles.title')}
                    description={t('rbac.roles.intro')}
                    actions={
                        can.create ? (
                            <Link
                                href={urls.create}
                                className={buttonClassName({
                                    variant: 'primary',
                                    size: 'sm',
                                })}
                            >
                                <Plus
                                    className="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                {t('rbac.roles.create')}
                            </Link>
                        ) : undefined
                    }
                />
            }
        >
            <Head title={t('rbac.roles.title')} />

            <div className="min-w-0 space-y-4">
                <div
                    className="flex flex-wrap items-center gap-2"
                    role="group"
                    aria-label={t('rbac.roles.scopeType')}
                >
                    <span className="mr-1 text-xs font-semibold text-(--app-muted-foreground)">
                        {t('rbac.roles.scopeType')}
                    </span>
                    {scopeTabs.map((tab) => {
                        const active = (filters.scope ?? '') === tab.value;
                        return (
                            <button
                                key={tab.value || 'all'}
                                type="button"
                                aria-pressed={active}
                                onClick={() => go({ scope: tab.value })}
                                className={cx(
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                    active
                                        ? 'border-primary bg-primary text-(--color-on-primary,#fff)'
                                        : 'border-(--app-border) text-(--app-muted-foreground) hover:bg-(--app-surface-muted)',
                                )}
                            >
                                {tab.value !== '' && (
                                    <span
                                        className={cx(
                                            'h-2 w-2 rounded-full',
                                            SCOPE_DOT[tab.value],
                                        )}
                                        aria-hidden="true"
                                    />
                                )}
                                {tab.label}
                                <span className="tabular-nums opacity-75">
                                    {tab.count}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <FilterPanel
                    label={t('rbac.roles.filters')}
                    onSubmit={() => go({})}
                    onReset={resetFilters}
                    hasActiveFilters={filtersActive}
                >
                    <FilterField label={t('shell.common.search')} wide>
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('rbac.roles.searchPlaceholder')}
                        />
                    </FilterField>
                </FilterPanel>

                <TableCard footer={<ListPagination meta={roles} bare />}>
                    {roles.data.length === 0 ? (
                        <EmptyState
                            title={
                                filtersActive
                                    ? t('shell.common.no_results')
                                    : t('rbac.roles.none')
                            }
                        />
                    ) : (
                        <TableTools id="roles-index">
                            <div className="relative overflow-x-auto">
                                <table
                                    className={cx(
                                        tableClassName,
                                        'min-w-full text-left',
                                    )}
                                >
                                    <thead className={tableHeadClassName}>
                                        <tr>
                                            {[
                                                t('rbac.roles.role'),
                                                t('rbac.roles.scopeType'),
                                                t('rbac.roles.permissionCount'),
                                                t('rbac.roles.usersCount'),
                                            ].map((h) => (
                                                <th
                                                    key={h}
                                                    scope="col"
                                                    className={cx(
                                                        tableHeaderCellClassName,
                                                        'text-left',
                                                    )}
                                                >
                                                    {h}
                                                </th>
                                            ))}
                                            <th
                                                scope="col"
                                                className={
                                                    tableHeaderCellClassName
                                                }
                                            >
                                                <span className="sr-only">
                                                    {t('shell.ui.actions')}
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className={tableBodyClassName}>
                                        {roles.data.map((r) => {
                                            return (
                                                <tr
                                                    key={r.id}
                                                    className={
                                                        tableRowClassName
                                                    }
                                                >
                                                    <td
                                                        className={
                                                            tableCellClassName
                                                        }
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="font-medium text-(--app-foreground)">
                                                                {r.label}
                                                            </span>
                                                            {r.is_protected && (
                                                                <span className="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                                                    {t(
                                                                        'rbac.roles.fullAccess',
                                                                    )}
                                                                </span>
                                                            )}
                                                            {r.is_system &&
                                                                !r.is_protected && (
                                                                    <span className="rounded-full bg-(--app-surface-muted) px-2 py-0.5 text-xs text-(--app-muted-foreground)">
                                                                        {t(
                                                                            'rbac.roles.defaultRole',
                                                                        )}
                                                                    </span>
                                                                )}
                                                        </div>
                                                        <span className="font-mono text-xs text-(--app-muted-foreground)">
                                                            {r.name}
                                                        </span>
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClassName
                                                        }
                                                    >
                                                        <span
                                                            className={cx(
                                                                'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                                                                r.scope_type ===
                                                                    'global'
                                                                    ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
                                                                    : 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
                                                            )}
                                                        >
                                                            {r.scope_type ===
                                                            'global'
                                                                ? t(
                                                                      'rbac.roles.globalRole',
                                                                  )
                                                                : t(
                                                                      'rbac.roles.branchRole',
                                                                  )}
                                                        </span>
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClassName
                                                        }
                                                    >
                                                        {r.is_protected ? (
                                                            <span className="text-xs text-(--app-muted-foreground)">
                                                                {t(
                                                                    'rbac.roles.fullAccess',
                                                                )}
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center rounded-full bg-(--app-surface-muted) px-2.5 py-0.5 text-xs font-medium text-(--app-foreground) tabular-nums">
                                                                {
                                                                    r.permissions_count
                                                                }
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td
                                                        className={cx(
                                                            tableCellClassName,
                                                            'text-(--app-muted-foreground) tabular-nums',
                                                        )}
                                                    >
                                                        {r.users_count}
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClassName
                                                        }
                                                    >
                                                        <div className="flex justify-end">
                                                            <AppActionMenu
                                                                items={[
                                                                    {
                                                                        label: t(
                                                                            'shell.actions.edit',
                                                                        ),
                                                                        href: r.editUrl,
                                                                        show: r
                                                                            .can
                                                                            .update,
                                                                    },
                                                                    {
                                                                        label: t(
                                                                            'shell.actions.delete',
                                                                        ),
                                                                        onClick:
                                                                            () =>
                                                                                void destroy(
                                                                                    r,
                                                                                ),
                                                                        variant:
                                                                            'danger',
                                                                        show: r
                                                                            .can
                                                                            .delete,
                                                                    },
                                                                ]}
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </TableTools>
                    )}
                </TableCard>
            </div>
        </AuthenticatedLayout>
    );
}

/** useConfirm() runs in the page, above the layout's own provider, so the page brings one. */
export default function RolesIndex(
    props: Parameters<typeof RolesIndexPage>[0],
) {
    return (
        <ConfirmProvider>
            <RolesIndexPage {...props} />
        </ConfirmProvider>
    );
}
