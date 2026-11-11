import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { VehicleTypeForm } from '@/components/vehicle-type-form';
import type { VehicleTypeFormData } from '@/components/vehicle-type-form';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { edit, index, update } from '@/routes/admin/vehicle-types';
import type { BreadcrumbItem } from '@/types';
import type { AdminVehicleType } from '@/types/admin';

type Props = {
    type: AdminVehicleType;
};

export default function VehicleTypesEdit({ type }: Props) {
    const { t } = useTranslation();
    const form = useForm<VehicleTypeFormData>({
        code: type.code,
        name_am: type.name_am,
        name_en: type.name_en,
        is_active: type.is_active,
        sort_order: String(type.sort_order),
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url({ vehicle_type: type.id }), {
            preserveScroll: true,
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.vehicle_types.title'), href: index() },
        {
            title: t('admin.vehicle_types.edit_title'),
            href: edit({ vehicle_type: type.id }),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.vehicle_types.edit_title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader title={t('admin.vehicle_types.edit_title')} />
                <VehicleTypeForm
                    form={form}
                    onSubmit={submit}
                    cancelHref={index.url()}
                    codeLocked
                />
            </div>
        </AppLayout>
    );
}
