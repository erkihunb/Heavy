import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FeeScheduleForm } from '@/components/fee-schedule-form';
import type { FeeScheduleFormData } from '@/components/fee-schedule-form';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, index, store } from '@/routes/admin/fee-schedules';
import type { BreadcrumbItem, VehicleType } from '@/types';

type Props = {
    vehicleTypes: VehicleType[];
};

export default function FeeSchedulesCreate({ vehicleTypes }: Props) {
    const { t } = useTranslation();

    const form = useForm<FeeScheduleFormData>({
        permit_type: '',
        vehicle_type_id: '',
        min_days: '',
        max_days: '',
        amount: '',
        effective_from: '',
        effective_to: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('fees.title'), href: index() },
        { title: t('fees.create_title'), href: create() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('fees.create_title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader title={t('fees.create_title')} />
                <FeeScheduleForm
                    form={form}
                    onSubmit={submit}
                    vehicleTypes={vehicleTypes}
                    cancelHref={index.url()}
                />
            </div>
        </AppLayout>
    );
}
