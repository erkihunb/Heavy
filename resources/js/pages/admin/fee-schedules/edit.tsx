import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FeeScheduleForm } from '@/components/fee-schedule-form';
import type { FeeScheduleFormData } from '@/components/fee-schedule-form';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { edit, index, update } from '@/routes/admin/fee-schedules';
import type { BreadcrumbItem, FeeSchedule, VehicleType } from '@/types';

type Props = {
    schedule: FeeSchedule;
    vehicleTypes: VehicleType[];
};

/** "150000" santim → "1500.00" birr, as typed in the form. */
function birr(cents: number): string {
    return `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;
}

export default function FeeSchedulesEdit({ schedule, vehicleTypes }: Props) {
    const { t } = useTranslation();

    const form = useForm<FeeScheduleFormData>({
        permit_type: schedule.permit_type,
        vehicle_type_id:
            schedule.vehicle_type_id === null
                ? ''
                : String(schedule.vehicle_type_id),
        min_days: String(schedule.min_days),
        max_days: String(schedule.max_days),
        amount: birr(schedule.amount.amount_cents),
        effective_from: schedule.effective_from.gregorian,
        effective_to: schedule.effective_to?.gregorian ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url({ fee_schedule: schedule.id }), {
            preserveScroll: true,
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('fees.title'), href: index() },
        {
            title: t('fees.edit_title'),
            href: edit({ fee_schedule: schedule.id }),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('fees.edit_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader title={t('fees.edit_title')} />
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
