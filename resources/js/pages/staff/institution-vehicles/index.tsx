import { Head, Link, router } from '@inertiajs/react';
import { FileCheck2, Plus } from 'lucide-react';
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

type VehicleRow = {
    id: string;
    plate: string;
    type: string;
    type_detail: string | null;
    registered_at: DateLabel;
    permit: { number: string; valid_to: DateLabel } | null;
    deletable: boolean;
    editUrl: string;
    issueUrl: string;
    destroyUrl: string;
};

/**
 * The register of the Authority's own vehicles: each with its type, the
 * permit it holds today, and a row menu to give it a permit, change it or
 * remove it. A vehicle that has held a permit is kept on the register, so
 * its permits keep naming it; its menu says why it cannot be removed.
 */
function InstitutionVehiclesPage({
    vehicles,
    filters,
    total,
    urls,
}: {
    vehicles: { data: VehicleRow[]; meta: LaravelPageMeta };
    filters: { search: string };
    total: number;
    urls: { index: string; create: string; permits: string; issue: string };
}) {
    const { t } = useLocale();
    const { confirm } = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const filtersActive = (filters.search ?? '') !== '';

    async function remove(vehicle: VehicleRow) {
        const { confirmed } = await confirm({
            title: t('institution.vehicle_delete_title').replace(
                ':plate',
                vehicle.plate,
            ),
            description: t('institution.vehicle_delete_body'),
            confirmLabel: t('shell.actions.delete'),
            variant: 'danger',
        });
        if (confirmed)
            router.delete(vehicle.destroyUrl, { preserveScroll: true });
    }

    const registerButton = (
        <Link href={urls.create} className={buttonClassName({})}>
            <Plus aria-hidden="true" className="size-4" />
            {t('institution.register_vehicle')}
        </Link>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('institution.vehicles_title')}
                    description={t('institution.vehicles_intro')}
                    actions={
                        <span className="flex flex-wrap gap-2">
                            {total > 0 && (
                                <Link
                                    href={urls.issue}
                                    className={buttonClassName({
                                        variant: 'outline',
                                    })}
                                >
                                    <FileCheck2
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    {t('institution.issue')}
                                </Link>
                            )}
                            {registerButton}
                        </span>
                    }
                />
            }
        >
            <Head title={t('institution.vehicles_title')} />

            <div className="space-y-4">
                {total > 0 && (
                    <FilterPanel
                        label={t('institution.filters_vehicles')}
                        onSubmit={() =>
                            visitWithFilters(urls.index, { search })
                        }
                        onReset={() => {
                            setSearch('');
                            visitWithFilters(urls.index, {});
                        }}
                        hasActiveFilters={filtersActive}
                    >
                        <FilterField label={t('shell.common.search')} wide>
                            <Input
                                type="search"
                                value={search}
                                placeholder={t(
                                    'institution.vehicle_search_placeholder',
                                )}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </FilterField>
                    </FilterPanel>
                )}

                <TableCard
                    footer={
                        vehicles.data.length > 0 && (
                            <ListPagination meta={vehicles.meta} bare />
                        )
                    }
                >
                    {vehicles.data.length === 0 ? (
                        <EmptyState
                            title={
                                filtersActive
                                    ? t('shell.common.no_results')
                                    : t('institution.no_vehicles')
                            }
                            action={filtersActive ? undefined : registerButton}
                        />
                    ) : (
                        <TableTools id="institution-vehicles-index">
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
                                                t('institution.columns.plate'),
                                                t('institution.columns.type'),
                                                t('institution.columns.model'),
                                                t('institution.columns.permit'),
                                                t(
                                                    'institution.columns.registered',
                                                ),
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
                                        {vehicles.data.map((vehicle) => (
                                            <tr
                                                key={vehicle.id}
                                                className={tableRowClassName}
                                            >
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'whitespace-nowrap',
                                                    )}
                                                >
                                                    <Link
                                                        href={vehicle.editUrl}
                                                        className="font-mono text-sm font-semibold text-(--app-foreground) hover:text-primary hover:underline"
                                                    >
                                                        {vehicle.plate}
                                                    </Link>
                                                </td>
                                                <td
                                                    className={
                                                        tableCellClassName
                                                    }
                                                >
                                                    {vehicle.type}
                                                </td>
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'text-(--app-muted-foreground)',
                                                    )}
                                                >
                                                    {vehicle.type_detail ?? '—'}
                                                </td>
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'whitespace-nowrap',
                                                    )}
                                                >
                                                    {vehicle.permit ? (
                                                        <span className="inline-flex flex-wrap items-center gap-2">
                                                            <span
                                                                className={cx(
                                                                    badgeClassName(
                                                                        'success',
                                                                    ),
                                                                    'font-mono',
                                                                )}
                                                            >
                                                                {
                                                                    vehicle
                                                                        .permit
                                                                        .number
                                                                }
                                                            </span>
                                                            <span className="text-xs text-(--app-muted-foreground)">
                                                                {
                                                                    vehicle
                                                                        .permit
                                                                        .valid_to
                                                                        ?.ethiopian_label
                                                                }
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span
                                                            className={badgeClassName(
                                                                'neutral',
                                                            )}
                                                        >
                                                            {t(
                                                                'institution.no_permit',
                                                            )}
                                                        </span>
                                                    )}
                                                </td>
                                                <td
                                                    className={cx(
                                                        tableCellClassName,
                                                        'whitespace-nowrap text-(--app-muted-foreground)',
                                                    )}
                                                >
                                                    {
                                                        vehicle.registered_at
                                                            ?.ethiopian_label
                                                    }
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
                                                                        'institution.give_permit',
                                                                    ),
                                                                    href: vehicle.issueUrl,
                                                                    show:
                                                                        vehicle.permit ===
                                                                        null,
                                                                },
                                                                {
                                                                    label: t(
                                                                        'shell.actions.edit',
                                                                    ),
                                                                    href: vehicle.editUrl,
                                                                },
                                                                {
                                                                    label: t(
                                                                        'shell.actions.delete',
                                                                    ),
                                                                    onClick:
                                                                        () =>
                                                                            void remove(
                                                                                vehicle,
                                                                            ),
                                                                    variant:
                                                                        'danger',
                                                                    disabled:
                                                                        !vehicle.deletable,
                                                                    title: vehicle.deletable
                                                                        ? undefined
                                                                        : t(
                                                                              'institution.cannot_delete',
                                                                          ),
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
export default function InstitutionVehicles(
    props: Parameters<typeof InstitutionVehiclesPage>[0],
) {
    return (
        <ConfirmProvider>
            <InstitutionVehiclesPage {...props} />
        </ConfirmProvider>
    );
}
