import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { visitWithFilters } from '@/lib/filters';
import EmptyState from '@/shell/Components/EmptyState';
import PageHeader from '@/shell/Components/PageHeader';
import StatCard from '@/shell/Components/StatCard';
import {
    FilterField,
    FilterPanel,
    Input,
    TableTools,
    badgeClassName,
    cx,
    tableBodyClassName,
    tableCellClassName,
    tableClassName,
    tableContainerClassName,
    tableHeadClassName,
    tableHeaderCellClassName,
    tableRowClassName,
} from '@/shell/Components/ui';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import { CRITICAL_PERMISSIONS } from '@/shell/lib/permissionGroups';

type PermissionRow = {
    name: string;
    label: string;
    description: string | null;
    roles_count: number;
    showUrl: string;
};

type Filters = { search: string };

/**
 * The permission catalogue in one list, read-only (EUISIS Permissions/Index without
 * create, edit or delete: in Heavy a permission exists because code checks
 * it, so new ones are added in code; see the README).
 */
export default function PermissionsIndex({
    permissions,
    stats,
    filters,
    urls,
}: {
    permissions: PermissionRow[];
    stats: { total: number; unassigned: number };
    filters: Filters;
    urls: { index: string };
}) {
    const { t } = useLocale();
    const [search, setSearch] = useState(filters.search ?? '');
    const filtersActive = Object.values(filters).some(Boolean);

    function go(next: Partial<Filters>) {
        visitWithFilters(urls.index, { ...filters, search, ...next });
    }

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('rbac.permissions.title')}
                    description={t('rbac.permissions.intro')}
                />
            }
        >
            <Head title={t('rbac.permissions.title')} />

            <div className="mb-5 grid gap-4 sm:grid-cols-2">
                <StatCard
                    label={t('rbac.permissions.statTotal')}
                    value={stats.total}
                    tone="primary"
                />
                <StatCard
                    label={t('rbac.permissions.statUnassigned')}
                    value={stats.unassigned}
                    tone={stats.unassigned > 0 ? 'warning' : 'success'}
                />
            </div>

            <div className="min-w-0 space-y-4">
                <FilterPanel
                    label={t('rbac.permissions.filters')}
                    onSubmit={() => go({})}
                    onReset={() => {
                        setSearch('');
                        router.get(
                            urls.index,
                            {},
                            { preserveScroll: true, replace: true },
                        );
                    }}
                    hasActiveFilters={filtersActive}
                >
                    <FilterField label={t('shell.common.search')} wide>
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('rbac.permissions.search')}
                        />
                    </FilterField>
                </FilterPanel>

                {permissions.length === 0 ? (
                    <div className={tableContainerClassName}>
                        <EmptyState title={t('rbac.permissions.noneFound')} />
                    </div>
                ) : (
                    <section className={tableContainerClassName}>
                        <TableTools id="permissions-index" toolbar={false}>
                            <div className="relative overflow-x-auto">
                                <table
                                    className={cx(
                                        tableClassName,
                                        'min-w-full text-left',
                                    )}
                                >
                                    <thead className={tableHeadClassName}>
                                        <tr>
                                            <th
                                                scope="col"
                                                className={
                                                    tableHeaderCellClassName
                                                }
                                            >
                                                {t(
                                                    'rbac.permissions.permission',
                                                )}
                                            </th>
                                            <th
                                                scope="col"
                                                className={
                                                    tableHeaderCellClassName
                                                }
                                            >
                                                {t('rbac.permissions.meaning')}
                                            </th>
                                            <th
                                                scope="col"
                                                className={
                                                    tableHeaderCellClassName
                                                }
                                            >
                                                {t('rbac.permissions.heldBy')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className={tableBodyClassName}>
                                        {permissions.map((p) => (
                                            <tr
                                                key={p.name}
                                                className={tableRowClassName}
                                            >
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    <Link
                                                        href={p.showUrl}
                                                        className="font-mono text-xs text-primary hover:underline"
                                                    >
                                                        {p.name}
                                                    </Link>
                                                    {CRITICAL_PERMISSIONS.has(
                                                        p.name,
                                                    ) && (
                                                        <span
                                                            title={t(
                                                                'rbac.permissions.criticalWarning',
                                                            )}
                                                            className={badgeClassName(
                                                                'danger',
                                                                'ml-2',
                                                            )}
                                                        >
                                                            {t(
                                                                'rbac.permissions.critical',
                                                            )}
                                                        </span>
                                                    )}
                                                </td>
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    <p className="text-sm text-(--app-foreground)">
                                                        {p.label}
                                                    </p>
                                                    {p.description && (
                                                        <p className="text-xs text-(--app-muted-foreground)">
                                                            {p.description}
                                                        </p>
                                                    )}
                                                </td>
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    <span
                                                        className={badgeClassName(
                                                            p.roles_count > 0
                                                                ? 'info'
                                                                : 'warning',
                                                        )}
                                                    >
                                                        {p.roles_count > 0
                                                            ? t(
                                                                  'rbac.permissions.rolesCount',
                                                              ).replace(
                                                                  ':count',
                                                                  String(
                                                                      p.roles_count,
                                                                  ),
                                                              )
                                                            : t(
                                                                  'rbac.permissions.unassigned',
                                                              )}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </TableTools>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
