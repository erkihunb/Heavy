import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Truck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { PlateChip } from '@/components/plate-chip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, edit, index } from '@/routes/vehicles';
import type { BreadcrumbItem, Paginated, Vehicle, VehicleType } from '@/types';

type Props = {
    vehicles: Paginated<Vehicle>;
    filters: {
        plate: string;
        vehicle_type: number | null;
    };
    vehicleTypes: VehicleType[];
};

export default function VehiclesIndex({
    vehicles,
    filters,
    vehicleTypes,
}: Props) {
    const { t } = useTranslation();
    const [plate, setPlate] = useState(filters.plate);
    const [vehicleType, setVehicleType] = useState(
        filters.vehicle_type === null ? '' : String(filters.vehicle_type),
    );

    const filtered = filters.plate !== '' || filters.vehicle_type !== null;

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            index.url(),
            {
                ...(plate.trim() !== '' && { plate: plate.trim() }),
                ...(vehicleType !== '' && { vehicle_type: vehicleType }),
            },
            { preserveState: true, replace: true },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('vehicles.title'), href: index() },
    ];

    const registerButton = (
        <Button asChild size="lg" className="w-full sm:w-auto">
            <Link href={create()}>
                <Plus aria-hidden="true" />
                {t('vehicles.register')}
            </Link>
        </Button>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('vehicles.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('vehicles.title')}
                    description={t('vehicles.intro')}
                    action={registerButton}
                />

                {vehicles.meta.total === 0 && !filtered ? (
                    <div className="flex flex-col items-center gap-4 rounded-3xl border-2 border-dashed border-input px-6 py-16 text-center">
                        <span className="flex size-16 items-center justify-center rounded-2xl border-2 border-foreground bg-signal text-signal-foreground">
                            <Truck aria-hidden="true" className="size-8" />
                        </span>
                        <h2 className="text-2xl text-display">
                            {t('vehicles.empty.title')}
                        </h2>
                        <p className="max-w-sm text-muted-foreground">
                            {t('vehicles.empty.body')}
                        </p>
                    </div>
                ) : (
                    <>
                        <form
                            role="search"
                            onSubmit={search}
                            className="grid gap-3 rounded-2xl border border-border bg-card p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,14rem)_auto_auto] sm:items-end"
                        >
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="filter-plate"
                                    className="text-eyebrow text-muted-foreground"
                                >
                                    {t('vehicles.filters.search')}
                                </label>
                                <Input
                                    id="filter-plate"
                                    type="search"
                                    value={plate}
                                    maxLength={50}
                                    onChange={(event) =>
                                        setPlate(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="filter-type"
                                    className="text-eyebrow text-muted-foreground"
                                >
                                    {t('vehicles.fields.vehicle_type')}
                                </label>
                                <NativeSelect
                                    id="filter-type"
                                    value={vehicleType}
                                    onChange={(event) =>
                                        setVehicleType(event.target.value)
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
                                </NativeSelect>
                            </div>
                            <Button type="submit" className="h-11">
                                <Search aria-hidden="true" />
                                {t('vehicles.filters.apply')}
                            </Button>
                            {filtered && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-11"
                                >
                                    <Link href={index()}>
                                        {t('vehicles.filters.clear')}
                                    </Link>
                                </Button>
                            )}
                        </form>

                        <p
                            className="text-sm text-muted-foreground tabular-nums"
                            aria-live="polite"
                        >
                            {vehicles.meta.total === 0
                                ? t('vehicles.no_results')
                                : t('vehicles.count', {
                                      count: vehicles.meta.total,
                                  })}
                        </p>

                        {vehicles.data.length > 0 && (
                            <>
                                {/* Phones: one card per vehicle */}
                                <ul className="grid gap-3 md:hidden">
                                    {vehicles.data.map((vehicle) => (
                                        <li
                                            key={vehicle.id}
                                            className="space-y-4 rounded-2xl border border-border bg-card p-4"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <PlateChip
                                                    plate={vehicle.plate}
                                                />
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={edit({
                                                            vehicle: vehicle.id,
                                                        })}
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                        {t('vehicles.edit')}
                                                    </Link>
                                                </Button>
                                            </div>
                                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <dt className="text-eyebrow text-muted-foreground">
                                                        {t(
                                                            'vehicles.fields.vehicle_type',
                                                        )}
                                                    </dt>
                                                    <dd className="font-medium">
                                                        {
                                                            vehicle.vehicle_type
                                                                .name
                                                        }
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-eyebrow text-muted-foreground">
                                                        {t(
                                                            'vehicles.fields.owner_name',
                                                        )}
                                                    </dt>
                                                    <dd className="font-medium">
                                                        {vehicle.owner_name}
                                                    </dd>
                                                </div>
                                                <div className="col-span-2">
                                                    <dt className="text-eyebrow text-muted-foreground">
                                                        {t(
                                                            'vehicles.fields.registered',
                                                        )}
                                                    </dt>
                                                    <dd>
                                                        <DualDate
                                                            date={
                                                                vehicle.registered_at
                                                            }
                                                        />
                                                    </dd>
                                                </div>
                                            </dl>
                                        </li>
                                    ))}
                                </ul>

                                {/* Wider screens: a table */}
                                <div className="hidden overflow-hidden rounded-2xl border border-border bg-card md:block">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-border bg-muted">
                                            <tr>
                                                {(
                                                    [
                                                        'plate',
                                                        'vehicle_type',
                                                        'owner_name',
                                                        'registered',
                                                    ] as const
                                                ).map((field) => (
                                                    <th
                                                        key={field}
                                                        scope="col"
                                                        className="px-5 py-3.5 text-eyebrow text-muted-foreground"
                                                    >
                                                        {t(
                                                            `vehicles.fields.${field}`,
                                                        )}
                                                    </th>
                                                ))}
                                                <th
                                                    scope="col"
                                                    className="px-5 py-3.5"
                                                >
                                                    <span className="sr-only">
                                                        {t('vehicles.edit')}
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {vehicles.data.map((vehicle) => (
                                                <tr
                                                    key={vehicle.id}
                                                    className="transition-colors hover:bg-muted/60"
                                                >
                                                    <th
                                                        scope="row"
                                                        className="px-5 py-4 font-normal"
                                                    >
                                                        <PlateChip
                                                            plate={
                                                                vehicle.plate
                                                            }
                                                        />
                                                    </th>
                                                    <td className="px-5 py-4 font-medium">
                                                        {
                                                            vehicle.vehicle_type
                                                                .name
                                                        }
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        {vehicle.owner_name}
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <DualDate
                                                            date={
                                                                vehicle.registered_at
                                                            }
                                                            stacked
                                                        />
                                                    </td>
                                                    <td className="px-5 py-4 text-right">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={edit({
                                                                    vehicle:
                                                                        vehicle.id,
                                                                })}
                                                                aria-label={`${t('vehicles.edit')} ${vehicle.plate}`}
                                                            >
                                                                <Pencil aria-hidden="true" />
                                                                {t(
                                                                    'vehicles.edit',
                                                                )}
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}

                        <Pagination
                            links={vehicles.links}
                            meta={vehicles.meta}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
