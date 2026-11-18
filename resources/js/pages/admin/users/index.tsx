import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { visitWithFilters } from '@/lib/filters';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import PageHeader from '@/shell/Components/PageHeader';
import {
    AppActionMenu,
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
import UserAvatar from '@/shell/Components/UserAvatar';
import { useConfirm } from '@/shell/hooks/useConfirm';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

export type UserRow = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    job_title: string | null;
    roles: { name: string; label: string }[];
    branch: { code: string; name: string } | null;
    is_staff: boolean;
    active: boolean;
    two_factor: boolean;
    created_at: {
        gregorian: string;
        ethiopian: string;
        ethiopian_label: string;
    } | null;
    editUrl: string;
};

type ListRow = UserRow & {
    last_login: { ethiopian_label: string; gregorian: string } | null;
    last_login_time: string | null;
    can: { update: boolean; deactivate: boolean; restore: boolean };
    deactivateUrl: string;
    restoreUrl: string;
};

type Filters = { search: string; role: string; status: string };

/** Everyone with an account, filtered by role and status (EUISIS Users/Index). */
function UsersIndexPage({
    users,
    filters,
    roleOptions,
    can,
    urls,
}: {
    users: { data: ListRow[]; meta: LaravelPageMeta };
    filters: Filters;
    roleOptions: { value: string; label: string }[];
    can: { create: boolean };
    urls: { index: string; create: string };
}) {
    const { t } = useLocale();
    const { confirm } = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const filtersActive = Object.values(filters).some(Boolean);

    function apply(next: Partial<Filters> = {}) {
        visitWithFilters(urls.index, { search, role, status, ...next });
    }

    async function deactivate(user: ListRow) {
        const { confirmed } = await confirm({
            title: t('admin.users.row_actions.confirm_deactivate').replace(
                ':name',
                user.name,
            ),
            description: t('admin.users.row_actions.confirm_deactivate_body'),
            confirmLabel: t('admin.users.row_actions.deactivate'),
            variant: 'danger',
        });

        if (confirmed) {
            router.post(user.deactivateUrl, {}, { preserveScroll: true });
        }
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
                                                t('admin.users.last_login'),
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
                                            <th scope="col">
                                                <span className="sr-only">
                                                    {t(
                                                        'admin.users.row_actions.label',
                                                    )}
                                                </span>
                                            </th>
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
                                                    <div className="flex items-center gap-3">
                                                        <UserAvatar
                                                            src={user.avatar}
                                                            name={user.name}
                                                            size={36}
                                                            className="shrink-0"
                                                        />
                                                        <div className="min-w-0">
                                                            <Link
                                                                href={
                                                                    user.editUrl
                                                                }
                                                                className="font-medium text-(--app-foreground) hover:text-primary hover:underline"
                                                            >
                                                                {user.name}
                                                            </Link>
                                                            <span className="block truncate text-xs text-(--app-muted-foreground)">
                                                                {user.job_title
                                                                    ? `${user.job_title} · ${user.email}`
                                                                    : user.email}
                                                            </span>
                                                        </div>
                                                    </div>
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
                                                                            'neutral',
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
                                                    {user.last_login ? (
                                                        <>
                                                            {
                                                                user.last_login
                                                                    .ethiopian_label
                                                            }
                                                            <span className="block text-xs">
                                                                {[
                                                                    user
                                                                        .last_login
                                                                        .gregorian,
                                                                    user.last_login_time,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(' ')}
                                                            </span>
                                                        </>
                                                    ) : (
                                                        t(
                                                            'admin.users.never_signed_in',
                                                        )
                                                    )}
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
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'w-px text-right',
                                                    )}
                                                >
                                                    <AppActionMenu
                                                        label={t(
                                                            'admin.users.row_actions.label',
                                                        )}
                                                        items={[
                                                            {
                                                                label: t(
                                                                    'admin.users.row_actions.edit',
                                                                ),
                                                                href: user.editUrl,
                                                                show: user.can
                                                                    .update,
                                                            },
                                                            {
                                                                label: t(
                                                                    'admin.users.row_actions.deactivate',
                                                                ),
                                                                onClick: () =>
                                                                    void deactivate(
                                                                        user,
                                                                    ),
                                                                variant:
                                                                    'danger',
                                                                show: user.can
                                                                    .deactivate,
                                                            },
                                                            {
                                                                label: t(
                                                                    'admin.users.row_actions.restore',
                                                                ),
                                                                href: user.restoreUrl,
                                                                method: 'post',
                                                                show: user.can
                                                                    .restore,
                                                            },
                                                        ]}
                                                    />
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

/** useConfirm() runs in the page, above the layout's own provider, so the page brings one. */
export default function UsersIndex(
    props: Parameters<typeof UsersIndexPage>[0],
) {
    return (
        <ConfirmProvider>
            <UsersIndexPage {...props} />
        </ConfirmProvider>
    );
}
