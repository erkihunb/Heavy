import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
import { permissionModuleLabel } from '@/shell/lib/permissionGroups';
import type { PermissionModule } from '@/shell/lib/permissionGroups';

type Scope = 'branch' | 'global';

type RoleRow = {
    id: number;
    name: string;
    label: string;
    name_am: string | null;
    scope_type: Scope;
    users_count: number;
    permissions_count: number;
    /** Module key → how many of the role's permissions fall in it, largest first. */
    modules: Record<string, number>;
    is_protected: boolean;
    is_system: boolean;
    can: { update: boolean; delete: boolean };
    editUrl: string;
    destroyUrl: string;
};

type Filters = { search: string; scope: string; module: string };

const SCOPE_DOT: Record<Scope, string> = {
    branch: 'bg-sky-500',
    global: 'bg-purple-500',
};
const MAX_MODULE_CHIPS = 3;

/** The roles, filtered by module and scope (EUISIS Roles/Index). */
function RolesIndexPage({
    roles,
    filters,
    modules,
    scopeCounts,
    can,
    urls,
}: {
    roles: { data: RoleRow[] } & LaravelPageMeta;
    filters: Filters;
    modules: PermissionModule[];
    scopeCounts: Record<Scope, number>;
    can: { create: boolean };
    urls: { index: string; create: string };
}) {
    const { t } = useLocale();
    const { confirm } = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const filtersActive = Object.values(filters).some(Boolean);
    const moduleName = (key: string) =>
        permissionModuleLabel(
            t,
            key,
            modules.find((m) => m.key === key)?.name ?? key,
        );

    function go(next: Partial<Filters>) {
        const params = { ...filters, search, ...next };
        router.get(
            urls.index,
            Object.fromEntries(Object.entries(params).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );
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

            <div className="grid gap-5 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <nav
                    aria-label={t('rbac.permissions.modules')}
                    className="self-start rounded-card border border-(--app-border) bg-(--app-surface) lg:sticky lg:top-20"
                >
                    <p className="border-b border-(--app-border) px-4 py-3 text-xs font-semibold text-(--app-muted-foreground)">
                        {t('rbac.permissions.modules')}
                    </p>
                    <ul className="flex gap-1 overflow-x-auto p-2 lg:max-h-[calc(100vh-8rem)] lg:flex-col lg:overflow-y-auto">
                        {[
                            {
                                key: '',
                                label: t('rbac.permissions.allModules'),
                            },
                            ...modules.map((m) => ({
                                key: m.key,
                                label: moduleName(m.key),
                            })),
                        ].map((item) => {
                            const isActive =
                                (filters.module ?? '') === item.key;
                            return (
                                <li
                                    key={item.key || 'all'}
                                    className="shrink-0"
                                >
                                    <button
                                        type="button"
                                        aria-current={
                                            isActive ? 'true' : undefined
                                        }
                                        onClick={() => go({ module: item.key })}
                                        className={cx(
                                            'w-full rounded-control px-3 py-2 text-left text-sm transition-colors',
                                            isActive
                                                ? 'bg-primary/10 font-semibold text-primary'
                                                : 'text-(--app-foreground) hover:bg-(--app-surface-muted)',
                                        )}
                                    >
                                        <span className="whitespace-nowrap lg:whitespace-normal">
                                            {item.label}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

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
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
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
                                                    t(
                                                        'rbac.roles.modulesCovered',
                                                    ),
                                                    t(
                                                        'rbac.roles.permissionCount',
                                                    ),
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
                                                const roleModules =
                                                    Object.entries(
                                                        r.modules ?? {},
                                                    );
                                                const hidden =
                                                    roleModules.slice(
                                                        MAX_MODULE_CHIPS,
                                                    );
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
                                                            className={cx(
                                                                tableCellClassName,
                                                                'max-w-md',
                                                            )}
                                                        >
                                                            {r.is_protected ? (
                                                                <span className="text-xs text-(--app-muted-foreground)">
                                                                    {t(
                                                                        'rbac.permissions.allModules',
                                                                    )}
                                                                </span>
                                                            ) : roleModules.length ===
                                                              0 ? (
                                                                <span className="text-xs text-(--app-muted-foreground)">
                                                                    —
                                                                </span>
                                                            ) : (
                                                                <div className="flex flex-wrap gap-1">
                                                                    {roleModules
                                                                        .slice(
                                                                            0,
                                                                            MAX_MODULE_CHIPS,
                                                                        )
                                                                        .map(
                                                                            ([
                                                                                key,
                                                                                count,
                                                                            ]) => (
                                                                                <span
                                                                                    key={
                                                                                        key
                                                                                    }
                                                                                    className="inline-flex items-center gap-1 rounded-full bg-(--app-surface-muted) px-2 py-0.5 text-xs text-(--app-foreground)"
                                                                                >
                                                                                    {moduleName(
                                                                                        key,
                                                                                    )}
                                                                                    <span className="text-(--app-muted-foreground) tabular-nums">
                                                                                        {
                                                                                            count
                                                                                        }
                                                                                    </span>
                                                                                </span>
                                                                            ),
                                                                        )}
                                                                    {hidden.length >
                                                                        0 && (
                                                                        <span
                                                                            title={hidden
                                                                                .map(
                                                                                    ([
                                                                                        key,
                                                                                        count,
                                                                                    ]) =>
                                                                                        `${moduleName(key)} (${count})`,
                                                                                )
                                                                                .join(
                                                                                    ', ',
                                                                                )}
                                                                            className="inline-flex items-center rounded-full px-2 py-0.5 text-xs text-(--app-muted-foreground)"
                                                                        >
                                                                            {t(
                                                                                'rbac.roles.moreModules',
                                                                            ).replace(
                                                                                ':count',
                                                                                String(
                                                                                    hidden.length,
                                                                                ),
                                                                            )}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            )}
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
