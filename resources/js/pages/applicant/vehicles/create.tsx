import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { VehicleForm } from '@/components/vehicle-form';
import type { VehicleFormData } from '@/components/vehicle-form';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, index, store } from '@/routes/vehicles';
import type { BreadcrumbItem, PlateRegionOption, VehicleType } from '@/types';

type Props = {
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
};

export default function VehiclesCreate({ vehicleTypes, plateRegions }: Props) {
    const { t } = useTranslation();

    const form = useForm<VehicleFormData>({
        plate: '',
        vehicle_type_id: '',
        owner_name: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('vehicles.title'), href: index() },
        { title: t('vehicles.register_title'), href: create() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('vehicles.register_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader title={t('vehicles.register_title')} />
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
