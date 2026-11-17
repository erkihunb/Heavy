import { Head, Link, router } from '@inertiajs/react';
import { Plus, Truck } from 'lucide-react';
import { useState } from 'react';
import { visitWithFilters } from '@/lib/filters';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import EmptyState from '@/shell/Components/EmptyState';
import PageHeader from '@/shell/Components/PageHeader';
import {
    AppActionMenu,
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
import { useConfirm } from '@/shell/hooks/useConfirm';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

type DateLabel = { ethiopian_label: string; gregorian: string } | null;

export type InstitutionVehicle = {
    id: string;
    plate: string;
    type: string;
    type_detail: string | null;
    permit: { number: string; valid_to: DateLabel } | null;
};

type InstitutionPermit = {
    id: string;
    number: string;
    plates: string[];
    vehicle_type: string | null;
    reason: string;
    route: string | null;
    valid_from: DateLabel;
    valid_to: DateLabel;
    issued_by: string | null;
    status: string;
    status_label: string;
    in_force: boolean;
    document_pending: boolean;
    document_urls: { am: string; en: string } | null;
    revokeUrl: string;
};

const statusTone = (
    status: string,
): 'success' | 'danger' | 'info' | 'neutral' =>
    status === 'active'
        ? 'success'
        : status === 'issued'
          ? 'info'
          : status === 'revoked'
            ? 'danger'
            : 'neutral';

/**
 * The permits given to the Authority's own vehicles. Each row's menu prints
 * its paper in either language and revokes it; revoking asks for the reason
 * in the confirmation dialog. The vehicles themselves are on their own page.
 */
function InstitutionPermitsPage({
    permits,
    vehicleCount,
    filters,
    statuses,
    urls,
}: {
    permits: { data: InstitutionPermit[]; meta: LaravelPageMeta };
    vehicleCount: number;
    filters: { search: string; status: string };
    statuses: { value: string; label: string }[];
    urls: { index: string; create: string; vehicles: string };
}) {
    const { t } = useLocale();
    const { confirm } = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const filtersActive =
        (filters.search ?? '') !== '' || (filters.status ?? '') !== '';

    function apply(next: Partial<typeof filters> = {}) {
        visitWithFilters(urls.index, { search, status, ...next });
    }

    async function revoke(permit: InstitutionPermit) {
        const { confirmed, reason } = await confirm({
            title: t('institution.revoke_title').replace(
                ':number',
                permit.number,
            ),
            description: t('institution.revoke_body'),
            confirmLabel: t('institution.revoke'),
            variant: 'danger',
            requireReason: true,
            reasonLabel: t('institution.fields.revoke_reason'),
        });
        if (confirmed)
            router.post(
                permit.revokeUrl,
                { reason: reason ?? '' },
                { preserveScroll: true },
            );
    }

    const print = (url: string | undefined) => {
        if (url) window.open(url, '_blank', 'noopener');
    };

    const giveButton = (
        <Link href={urls.create} className={buttonClassName({})}>
            <Plus aria-hidden="true" className="size-4" />
            {t('institution.issue')}
        </Link>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('institution.title')}
                    description={t('institution.intro')}
                    actions={
                        <span className="flex flex-wrap gap-2">
                            <Link
                                href={urls.vehicles}
                                className={buttonClassName({
                                    variant: 'outline',
                                })}
                            >
                                <Truck aria-hidden="true" className="size-4" />
                                {t('institution.go_to_vehicles')}
                            </Link>
                            {vehicleCount > 0 && giveButton}
                        </span>
                    }
                />
            }
        >
            <Head title={t('institution.title')} />

            <div className="space-y-4">
                <FilterPanel
                    label={t('institution.filters_permits')}
                    onSubmit={() => apply()}
                    onReset={() => {
                        setSearch('');
                        setStatus('');
                        visitWithFilters(urls.index, {});
                    }}
                    hasActiveFilters={filtersActive}
                >
                    <FilterField label={t('shell.common.search')} wide>
                        <Input
                            type="search"
                            value={search}
                            placeholder={t('institution.search_placeholder')}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </FilterField>
                    <FilterField label={t('institution.columns.status')}>
                        <Select
                            value={status}
                            onChange={(event) => {
                                setStatus(event.target.value);
                                apply({ status: event.target.value });
                            }}
                        >
                            <option value="">
                                {t('institution.any_status')}
                            </option>
                            {statuses.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </Select>
                    </FilterField>
                </FilterPanel>

                <TableCard
                    footer={
                        permits.data.length > 0 && (
                            <ListPagination meta={permits.meta} bare />
                        )
                    }
                >
                    {permits.data.length === 0 ? (
                        <EmptyState
                            title={
                                filtersActive
                                    ? t('shell.common.no_results')
                                    : vehicleCount === 0
                                      ? t('institution.no_vehicles')
                                      : t('institution.no_permits')
                            }
                            action={
                                filtersActive ? undefined : vehicleCount ===
                                  0 ? (
                                    <Link
                                        href={urls.vehicles}
                                        className={buttonClassName({})}
                                    >
                                        <Truck
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        {t('institution.go_to_vehicles')}
                                    </Link>
                                ) : (
                                    giveButton
                                )
                            }
                        />
                    ) : (
                        <TableTools id="institution-permits-index">
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
                                                t('institution.columns.number'),
                                                t(
                                                    'institution.columns.vehicle',
                                                ),
                                                t('institution.purpose'),
                                                t('institution.columns.period'),
                                                t(
                                                    'institution.columns.issued_by',
                                                ),
                                                t('institution.columns.status'),
                                                t('institution.columns.paper'),
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
                                            <th
                                                scope="col"
                                                className={cx(
                                                    tableHeaderCellClassName,
                                                    'w-12 text-right',
                                                )}
                                            >
                                                <span className="sr-only">
                                                    {t('shell.ui.actions')}
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className={tableBodyClassName}>
                                        {permits.data.map((permit) => (
                                            <tr
                                                key={permit.id}
                                                className={tableRowClassName}
                                            >
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'font-mono text-sm font-semibold whitespace-nowrap text-(--app-foreground)',
                                                    )}
                                                >
                                                    {permit.number}
                                                </td>
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'whitespace-nowrap',
                                                    )}
                                                >
                                                    <span className="font-mono text-sm font-medium text-(--app-foreground)">
                                                        {permit.plates.join(
                                                            ', ',
                                                        )}
                                                    </span>
                                                    <span className="block text-xs text-(--app-muted-foreground)">
                                                        {permit.vehicle_type}
                                                    </span>
                                                </td>
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    <span className="block max-w-72 truncate">
                                                        {permit.reason}
                                                    </span>
                                                    <span className="block max-w-72 truncate text-xs text-(--app-muted-foreground)">
                                                        {permit.route ??
                                                            t(
                                                                'institution.route_any',
                                                            )}
                                                    </span>
                                                </td>
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'whitespace-nowrap',
                                                    )}
                                                >
                                                    {
                                                        permit.valid_from
                                                            ?.ethiopian_label
                                                    }
                                                    <span className="block text-xs text-(--app-muted-foreground)">
                                                        –{' '}
                                                        {
                                                            permit.valid_to
                                                                ?.ethiopian_label
                                                        }
                                                    </span>
                                                </td>
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'text-(--app-muted-foreground)',
                                                    )}
                                                >
                                                    {permit.issued_by}
                                                </td>
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    <span
                                                        className={badgeClassName(
                                                            statusTone(
                                                                permit.status,
                                                            ),
                                                        )}
                                                    >
                                                        {permit.status_label}
                                                    </span>
                                                </td>
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'text-xs whitespace-nowrap text-(--app-muted-foreground)',
                                                    )}
                                                >
                                                    {permit.document_urls
                                                        ? t(
                                                              'institution.paper_ready',
                                                          )
                                                        : permit.document_pending
                                                          ? t(
                                                                'institution.paper_pending',
                                                            )
                                                          : '—'}
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
                                                                        'institution.print_am',
                                                                    ),
                                                                    onClick:
                                                                        () =>
                                                                            print(
                                                                                permit
                                                                                    .document_urls
                                                                                    ?.am,
                                                                            ),
                                                                    disabled:
                                                                        permit.document_urls ===
                                                                        null,
                                                                    title:
                                                                        permit.document_urls ===
                                                                        null
                                                                            ? t(
                                                                                  'institution.preparing',
                                                                              )
                                                                            : undefined,
                                                                },
                                                                {
                                                                    label: t(
                                                                        'institution.print_en',
                                                                    ),
                                                                    onClick:
                                                                        () =>
                                                                            print(
                                                                                permit
                                                                                    .document_urls
                                                                                    ?.en,
                                                                            ),
                                                                    disabled:
                                                                        permit.document_urls ===
                                                                        null,
                                                                    title:
                                                                        permit.document_urls ===
                                                                        null
                                                                            ? t(
                                                                                  'institution.preparing',
                                                                              )
                                                                            : undefined,
                                                                },
                                                                {
                                                                    label: t(
                                                                        'institution.revoke',
                                                                    ),
                                                                    onClick:
                                                                        () =>
                                                                            void revoke(
                                                                                permit,
                                                                            ),
                                                                    variant:
                                                                        'danger',
                                                                    show: permit.in_force,
                                                                    separatorBefore: true,
                                                                },
                                                            ]}
                                                        />
                                                    </div>
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
export default function InstitutionPermits(
    props: Parameters<typeof InstitutionPermitsPage>[0],
) {
    return (
        <ConfirmProvider>
            <InstitutionPermitsPage {...props} />
        </ConfirmProvider>
    );
}
