import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { visitWithFilters } from '@/lib/filters';
import { Plus } from '@/shell/Components/Icons';
import PageHeader from '@/shell/Components/PageHeader';
import {
    EmptyState,
    FilterField,
    FilterPanel,
    Input,
    ListPagination,
    Select,
    TableCard,
    TableTools,
    badgeClassName,
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
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

export type UserRow = {
    id: number;
    name: string;
    email: string;
    roles: { name: string; label: string }[];
    branch: { code: string; name: string } | null;
    is_staff: boolean;
    is_admin: boolean;
    active: boolean;
    two_factor: boolean;
    created_at: {
        gregorian: string;
        ethiopian: string;
        ethiopian_label: string;
    } | null;
    editUrl: string;
};

type Filters = { search: string; role: string; status: string };

/** Everyone with an account, filtered by role and status (EUISIS Users/Index). */
export default function UsersIndex({
    users,
    filters,
    roleOptions,
    can,
    urls,
}: {
    users: { data: UserRow[]; meta: LaravelPageMeta };
    filters: Filters;
    roleOptions: { value: string; label: string }[];
    can: { create: boolean };
    urls: { index: string; create: string };
}) {
    const { t } = useLocale();
    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const filtersActive = Object.values(filters).some(Boolean);

    function apply(next: Partial<Filters> = {}) {
        visitWithFilters(urls.index, { search, role, status, ...next });
    }

    function reset() {
        setSearch('');
        setRole('');
        setStatus('');
        router.get(urls.index, {}, { preserveScroll: true, replace: true });
    }

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('admin.users.title')}
                    description={t('rbac.users.intro')}
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
                                {t('admin.users.new')}
                            </Link>
                        ) : undefined
                    }
                />
            }
        >
            <Head title={t('admin.users.title')} />

            <div className="space-y-4">
                <FilterPanel
                    label={t('rbac.users.filters')}
                    onSubmit={() => apply()}
                    onReset={reset}
                    hasActiveFilters={filtersActive}
                >
                    <FilterField label={t('shell.common.search')} wide>
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('admin.users.search_placeholder')}
                        />
                    </FilterField>
                    <FilterField label={t('admin.users.filter_role')}>
                        <Select
                            value={role}
                            onChange={(event) => {
                                setRole(event.target.value);
                                apply({ role: event.target.value });
                            }}
                        >
                            <option value="">
                                {t('admin.users.any_role')}
                            </option>
                            {roleOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                            <option value="none">
                                {t('admin.users.no_role')}
                            </option>
                        </Select>
                    </FilterField>
                    <FilterField label={t('rbac.users.status')}>
                        <Select
                            value={status}
                            onChange={(event) => {
                                setStatus(event.target.value);
                                apply({ status: event.target.value });
                            }}
                        >
                            <option value="">
                                {t('rbac.users.anyStatus')}
                            </option>
                            <option value="active">
                                {t('rbac.users.active')}
                            </option>
                            <option value="inactive">
                                {t('rbac.users.inactive')}
                            </option>
                        </Select>
                    </FilterField>
                </FilterPanel>

                <TableCard footer={<ListPagination meta={users.meta} bare />}>
                    {users.data.length === 0 ? (
                        <EmptyState
                            title={
                                filtersActive
                                    ? t('shell.common.no_results')
                                    : t('admin.users.empty')
                            }
                        />
                    ) : (
                        <TableTools id="users-index">
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
                                                t('admin.users.fields.name'),
                                                t('rbac.users.fields.roles'),
                                                t('rbac.users.worksAt'),
                                                t('rbac.users.status'),
                                                t('admin.users.joined'),
                                            ].map((heading) => (
                                                <th
                                                    key={heading}
                                                    scope="col"
                                                    className={cx(
                                                        tableHeaderCellClassName,
                                                        'text-left',
                                                    )}
                                                >
                                                    {heading}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className={tableBodyClassName}>
                                        {users.data.map((user) => (
                                            <tr
                                                key={user.id}
                                                className={tableRowClassName}
                                            >
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    <Link
                                                        href={user.editUrl}
                                                        className="font-medium text-(--app-foreground) hover:text-primary hover:underline"
                                                    >
                                                        {user.name}
                                                    </Link>
                                                    <span className="block text-xs text-(--app-muted-foreground)">
                                                        {user.email}
                                                    </span>
                                                </td>
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    {user.roles.length === 0 ? (
                                                        <span className="text-xs text-(--app-muted-foreground)">
                                                            {t(
                                                                'admin.users.no_role',
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="flex flex-wrap gap-1">
                                                            {user.roles.map(
                                                                (item) => (
                                                                    <span
                                                                        key={
                                                                            item.name
                                                                        }
                                                                        className={badgeClassName(
                                                                            item.name ===
                                                                                'admin'
                                                                                ? 'info'
                                                                                : 'neutral',
                                                                        )}
                                                                    >
                                                                        {
                                                                            item.label
                                                                        }
                                                                    </span>
                                                                ),
                                                            )}
                                                        </span>
                                                    )}
                                                </td>
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    {!user.is_staff ? (
                                                        <span className="text-(--app-muted-foreground)">
                                                            —
                                                        </span>
                                                    ) : user.branch ? (
                                                        <>
                                                            <span className="font-mono text-xs text-(--app-muted-foreground)">
                                                                {
                                                                    user.branch
                                                                        .code
                                                                }
                                                            </span>{' '}
                                                            {user.branch.name}
                                                        </>
                                                    ) : (
                                                        t(
                                                            'rbac.scopes.headOffice',
                                                        )
                                                    )}
                                                </td>
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    <span className="flex flex-wrap gap-1">
                                                        <span
                                                            className={badgeClassName(
                                                                user.active
                                                                    ? 'success'
                                                                    : 'danger',
                                                            )}
                                                        >
                                                            {user.active
                                                                ? t(
                                                                      'rbac.users.active',
                                                                  )
                                                                : t(
                                                                      'rbac.users.inactive',
                                                                  )}
                                                        </span>
                                                        {user.is_staff && (
                                                            <span
                                                                className={badgeClassName(
                                                                    user.two_factor
                                                                        ? 'neutral'
                                                                        : 'warning',
                                                                )}
                                                            >
                                                                {user.two_factor
                                                                    ? t(
                                                                          'admin.users.two_factor_on',
                                                                      )
                                                                    : t(
                                                                          'admin.users.two_factor_off',
                                                                      )}
                                                            </span>
                                                        )}
                                                    </span>
                                                </td>
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'whitespace-nowrap text-(--app-muted-foreground)',
                                                    )}
                                                >
                                                    {user.created_at ? (
                                                        <>
                                                            {
                                                                user.created_at
                                                                    .ethiopian_label
                                                            }
                                                            <span className="block text-xs">
                                                                {
                                                                    user
                                                                        .created_at
                                                                        .gregorian
                                                                }
                                                            </span>
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
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
