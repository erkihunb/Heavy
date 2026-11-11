import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { VehicleTypeForm } from '@/components/vehicle-type-form';
import type { VehicleTypeFormData } from '@/components/vehicle-type-form';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, index, store } from '@/routes/admin/vehicle-types';
import type { BreadcrumbItem } from '@/types';

export default function VehicleTypesCreate() {
    const { t } = useTranslation();
    const form = useForm<VehicleTypeFormData>({
        code: '',
        name_am: '',
        name_en: '',
        is_active: true,
        sort_order: '0',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.vehicle_types.title'), href: index() },
        { title: t('admin.vehicle_types.create_title'), href: create() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.vehicle_types.create_title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader title={t('admin.vehicle_types.create_title')} />
                <VehicleTypeForm
                    form={form}
                    onSubmit={submit}
                    cancelHref={index.url()}
                    codeLocked={false}
                />
            </div>
        </AppLayout>
    );
}
