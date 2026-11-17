import { Head, Link, router } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
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
    /** The names of the roles that hold it. */
    roles: string[];
    roles_count: number;
    showUrl: string;
};

type RoleColumn = { name: string; label: string };

type Filters = { search: string };

/**
 * The permission catalogue as a matrix, read-only: a row per permission, a
 * column per role and a tick where the role holds it (EUISIS Permissions/Index
 * without create, edit or delete: in Heavy a permission exists because code
 * checks it, so new ones are added in code; see the README). The rows are one
 * list in catalogue order: permissions are not put into categories.
 */
export default function PermissionsIndex({
    permissions,
    roles,
    stats,
    filters,
    urls,
}: {
    permissions: PermissionRow[];
    roles: RoleColumn[];
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
                                                className={cx(
                                                    tableHeaderCellClassName,
                                                    // The permission stays in view while the role columns scroll sideways.
                                                    'sticky left-0 z-1 bg-(--app-surface-muted)',
                                                )}
                                            >
                                                {t(
                                                    'rbac.permissions.permission',
                                                )}
                                            </th>
                                            {roles.map((role) => (
                                                <th
                                                    key={role.name}
                                                    scope="col"
                                                    className={cx(
                                                        tableHeaderCellClassName,
                                                        'text-center whitespace-nowrap',
                                                    )}
                                                >
                                                    {role.label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className={tableBodyClassName}>
                                        {permissions.map((p) => (
                                            <tr
                                                key={p.name}
                                                className={cx(
                                                    tableRowClassName,
                                                    'group',
                                                )}
                                            >
                                                <th
                                                    scope="row"
                                                    className={cx(
                                                        tableCellClassName,
                                                        'sticky left-0 z-1 max-w-sm min-w-64 bg-(--app-surface) text-left font-normal transition-colors group-hover:bg-(--app-surface-muted)',
                                                    )}
                                                >
                                                    <p className="text-sm font-medium text-(--app-foreground)">
                                                        {p.label}
                                                    </p>
                                                    <p className="mt-0.5 flex flex-wrap items-center gap-2">
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
                                                                )}
                                                            >
                                                                {t(
                                                                    'rbac.permissions.critical',
                                                                )}
                                                            </span>
                                                        )}
                                                        {p.roles_count ===
                                                            0 && (
                                                            <span
                                                                title={t(
                                                                    'rbac.permissions.unassignedHelp',
                                                                )}
                                                                className={badgeClassName(
                                                                    'warning',
                                                                )}
                                                            >
                                                                {t(
                                                                    'rbac.permissions.unassigned',
                                                                )}
                                                            </span>
                                                        )}
                                                    </p>
                                                    {p.description && (
                                                        <p className="mt-1 text-xs text-(--app-muted-foreground)">
                                                            {p.description}
                                                        </p>
                                                    )}
                                                </th>
                                                {roles.map((role) => {
                                                    const granted =
                                                        p.roles.includes(
                                                            role.name,
                                                        );
                                                    return (
                                                        <td
                                                            key={role.name}
                                                            className={cx(
                                                                tableCellClassName,
                                                                'text-center',
                                                            )}
                                                        >
                                                            {granted ? (
                                                                <Check
                                                                    aria-hidden="true"
                                                                    className="mx-auto h-4 w-4 text-success-strong"
                                                                />
                                                            ) : (
                                                                <Minus
                                                                    aria-hidden="true"
                                                                    className="mx-auto h-4 w-4 text-(--app-muted-foreground) opacity-40"
                                                                />
                                                            )}
                                                            <span className="sr-only">
                                                                {t(
                                                                    granted
                                                                        ? 'rbac.permissions.granted'
                                                                        : 'rbac.permissions.notGranted',
                                                                )}
                                                            </span>
                                                        </td>
                                                    );
                                                })}
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
