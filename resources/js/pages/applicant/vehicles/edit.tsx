import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { VehicleForm } from '@/components/vehicle-form';
import type { VehicleFormData } from '@/components/vehicle-form';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { edit, index, update } from '@/routes/vehicles';
import type {
    BreadcrumbItem,
    PlateRegionOption,
    Vehicle,
    VehicleType,
} from '@/types';

type Props = {
    vehicle: Vehicle;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
};

export default function VehiclesEdit({
    vehicle,
    vehicleTypes,
    plateRegions,
}: Props) {
    const { t } = useTranslation();

    const form = useForm<VehicleFormData>({
        plate: vehicle.plate,
        vehicle_type_id: String(vehicle.vehicle_type.id),
        owner_name: vehicle.owner_name,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url({ vehicle: vehicle.id }), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('vehicles.title'), href: index() },
        { title: vehicle.plate, href: edit({ vehicle: vehicle.id }) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('vehicles.edit_title')} ${vehicle.plate}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader title={t('vehicles.edit_title')} />
                <VehicleForm
                    form={form}
                    onSubmit={submit}
                    vehicleTypes={vehicleTypes}
                    plateRegions={plateRegions}
                />
            </div>
        </AppLayout>
    );
}
