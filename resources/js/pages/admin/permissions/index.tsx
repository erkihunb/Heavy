import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
import {
    CRITICAL_PERMISSIONS,
    permissionGroupLabel,
    permissionModuleLabel,
} from '@/shell/lib/permissionGroups';
import type { PermissionModule } from '@/shell/lib/permissionGroups';

type PermissionRow = {
    name: string;
    label: string;
    description: string | null;
    group: string;
    module: string;
    roles_count: number;
    showUrl: string;
};

type Filters = { search: string; module: string };

/**
 * The permission catalogue, read-only (EUISIS Permissions/Index without
 * create, edit or delete: in Heavy a permission exists because code checks
 * it, so new ones are added in code; see the README).
 */
export default function PermissionsIndex({
    permissions,
    modules,
    stats,
    filters,
    urls,
}: {
    permissions: PermissionRow[];
    modules: PermissionModule[];
    stats: { total: number; modules: number; unassigned: number };
    filters: Filters;
    urls: { index: string };
}) {
    const { t } = useLocale();
    const [search, setSearch] = useState(filters.search ?? '');
    const moduleName = (key: string) =>
        permissionModuleLabel(
            t,
            key,
            modules.find((m) => m.key === key)?.name ?? key,
        );
    const filtersActive = Object.values(filters).some(Boolean);

    function go(next: Partial<Filters>) {
        const params = { ...filters, search, ...next };
        router.get(
            urls.index,
            Object.fromEntries(Object.entries(params).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    // Rows in catalogue order, under a heading per group.
    const byGroup = permissions.reduce<Record<string, PermissionRow[]>>(
        (acc, row) => {
            (acc[row.group] ??= []).push(row);
            return acc;
        },
        {},
    );

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

            <div className="mb-5 grid gap-4 sm:grid-cols-3">
                <StatCard
                    label={t('rbac.permissions.statTotal')}
                    value={stats.total}
                    tone="primary"
                />
                <StatCard
                    label={t('rbac.permissions.statModules')}
                    value={stats.modules}
                    tone="neutral"
                />
                <StatCard
                    label={t('rbac.permissions.statUnassigned')}
                    value={stats.unassigned}
                    tone={stats.unassigned > 0 ? 'warning' : 'success'}
                />
            </div>

            <div className="grid gap-5 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <nav
                    aria-label={t('rbac.permissions.modules')}
                    className="self-start rounded-card border border-(--app-border) bg-(--app-surface) lg:sticky lg:top-20"
                >
                    <p className="border-b border-(--app-border) px-4 py-3 text-xs font-semibold text-(--app-muted-foreground)">
                        {t('rbac.permissions.modules')}
                    </p>
                    <ul className="flex gap-1 overflow-x-auto p-2 lg:flex-col">
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
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t('rbac.permissions.search')}
                            />
                        </FilterField>
                    </FilterPanel>

                    {permissions.length === 0 ? (
                        <div className={tableContainerClassName}>
                            <EmptyState
                                title={t('rbac.permissions.noneFound')}
                            />
                        </div>
                    ) : (
                        Object.entries(byGroup).map(([group, rows]) => (
                            <section
                                key={group}
                                aria-labelledby={`group-${group}`}
                                className={tableContainerClassName}
                            >
                                <h2
                                    id={`group-${group}`}
                                    className="flex items-center gap-2 border-b border-(--app-border) bg-(--app-surface-muted) px-4 py-2.5 text-xs font-semibold text-(--app-foreground)"
                                >
                                    {permissionGroupLabel(t, group)}
                                    <span className="font-normal text-(--app-muted-foreground)">
                                        · {moduleName(rows[0].module)}
                                    </span>
                                </h2>
                                <TableTools
                                    id={`permissions-${group}`}
                                    toolbar={false}
                                >
                                    <div className="relative overflow-x-auto">
                                        <table
                                            className={cx(
                                                tableClassName,
                                                'min-w-full text-left',
                                            )}
                                        >
                                            <thead
                                                className={tableHeadClassName}
                                            >
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
                                                        {t(
                                                            'rbac.permissions.meaning',
                                                        )}
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className={
                                                            tableHeaderCellClassName
                                                        }
                                                    >
                                                        {t(
                                                            'rbac.permissions.heldBy',
                                                        )}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                className={tableBodyClassName}
                                            >
                                                {rows.map((p) => (
                                                    <tr
                                                        key={p.name}
                                                        className={
                                                            tableRowClassName
                                                        }
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
                                                                    {
                                                                        p.description
                                                                    }
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
                                                                    p.roles_count >
                                                                        0
                                                                        ? 'info'
                                                                        : 'warning',
                                                                )}
                                                            >
                                                                {p.roles_count >
                                                                0
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
                        ))
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
