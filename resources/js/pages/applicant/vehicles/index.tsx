import { Head, Link } from '@inertiajs/react';
import { DualDate } from '@/components/dual-date';
import { PlateChip } from '@/components/plate-chip';
import { useTranslation } from '@/lib/i18n';
import { create, edit, index } from '@/routes/vehicles';
import { Plus } from '@/shell/Components/Icons';
import { DeliveryIcon } from '@/shell/Components/NavIcons';
import PageHeader from '@/shell/Components/PageHeader';
import {
    AppDataTable,
    AppFilterBar,
    Button,
    Card,
    EmptyState,
    FilterField,
    Input,
    Select,
    createColumnHelper,
} from '@/shell/Components/ui';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import type { Paginated, Vehicle, VehicleType } from '@/types';

type Props = {
    vehicles: Paginated<Vehicle>;
    filters: {
        plate: string;
        vehicle_type: number | null;
    };
    vehicleTypes: VehicleType[];
};

const column = createColumnHelper<Vehicle>();

/**
 * The applicant's vehicles, in the admin shell: filter panel, data table
 * (sortable headers, column and density tools) and numbered pagination.
 */
export default function VehiclesIndex({
    vehicles,
    filters,
    vehicleTypes,
}: Props) {
    const { t } = useTranslation();
    const filtered = filters.plate !== '' || filters.vehicle_type !== null;

    const columns = [
        column.accessor('plate', {
            header: t('vehicles.fields.plate'),
            cell: ({ row }) => <PlateChip plate={row.original.plate} />,
        }),
        column.accessor((vehicle) => vehicle.vehicle_type.name, {
            id: 'vehicle_type',
            header: t('vehicles.fields.vehicle_type'),
            cell: ({ getValue }) => (
                <span className="font-medium">{getValue()}</span>
            ),
        }),
        column.accessor('owner_name', {
            header: t('vehicles.fields.owner_name'),
        }),
        column.display({
            id: 'registered',
            header: t('vehicles.fields.registered'),
            cell: ({ row }) => (
                <DualDate date={row.original.registered_at} stacked />
            ),
        }),
        column.display({
            id: 'actions',
            header: () => <span className="sr-only">{t('vehicles.edit')}</span>,
            cell: ({ row }) => (
                <div className="text-right">
                    <Button
                        as={Link}
                        href={edit({ vehicle: row.original.id }).url}
                        variant="outline"
                        size="sm"
                        aria-label={`${t('vehicles.edit')} ${row.original.plate}`}
                    >
                        {t('vehicles.edit')}
                    </Button>
                </div>
            ),
        }),
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('vehicles.title')}
                    description={t('vehicles.intro')}
                    actions={
                        <Button
                            as={Link}
                            href={create().url}
                            size="lg"
                            icon={<Plus />}
                            className="w-full sm:w-auto"
                        >
                            {t('vehicles.register')}
                        </Button>
                    }
                />
            }
        >
            <Head title={t('vehicles.title')} />

            {vehicles.meta.total === 0 && !filtered ? (
                <Card>
                    <EmptyState
                        icon={
                            <span className="flex size-16 items-center justify-center rounded-card bg-highlight text-highlight-foreground">
                                <DeliveryIcon className="size-8" />
                            </span>
                        }
                        title={t('vehicles.empty.title')}
                        description={t('vehicles.empty.body')}
                    />
                </Card>
            ) : (
                <div className="flex flex-col gap-4">
                    <AppFilterBar
                        href={index.url()}
                        filters={{
                            plate: filters.plate,
                            vehicle_type:
                                filters.vehicle_type === null
                                    ? null
                                    : String(filters.vehicle_type),
                        }}
                        label={t('vehicles.filters.search')}
                    >
                        <FilterField label={t('vehicles.filters.search')} wide>
                            <Input
                                type="search"
                                name="plate"
                                maxLength={50}
                                defaultValue={filters.plate}
                            />
                        </FilterField>
                        <FilterField label={t('vehicles.fields.vehicle_type')}>
                            <Select
                                name="vehicle_type"
                                defaultValue={
                                    filters.vehicle_type === null
                                        ? ''
                                        : String(filters.vehicle_type)
                                }
                            >
                                <option value="">
                                    {t('vehicles.filters.all_types')}
                                </option>
                                {vehicleTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </Select>
                        </FilterField>
                    </AppFilterBar>

                    {/* Announced after every search; with no match the table's empty state shows the same words. */}
                    <p
                        className={
                            vehicles.meta.total === 0
                                ? 'sr-only'
                                : 'text-sm text-(--app-muted-foreground) tabular-nums'
                        }
                        aria-live="polite"
                    >
                        {vehicles.meta.total === 0
                            ? t('vehicles.no_results')
                            : t('vehicles.count', {
                                  count: vehicles.meta.total,
                              })}
                    </p>

                    <AppDataTable
                        id="applicant-vehicles"
                        data={vehicles.data}
                        columns={columns}
                        meta={vehicles.meta}
                        filters={{
                            plate: filters.plate,
                            vehicle_type: filters.vehicle_type,
                        }}
                        href={index.url()}
                        emptyTitle={t('vehicles.no_results')}
                    />
                </div>
            )}
        </AuthenticatedLayout>
    );
}
