import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FeeScheduleForm } from '@/components/fee-schedule-form';
import type { FeeScheduleFormData } from '@/components/fee-schedule-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, update } from '@/routes/admin/fee-schedules';
import type { FeeSchedule, VehicleType } from '@/types';

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

    return (
        <FormPageLayout title={t('fees.edit_title')} backHref={index.url()}>
            <FeeScheduleForm
                form={form}
                onSubmit={submit}
                vehicleTypes={vehicleTypes}
                cancelHref={index.url()}
            />
        </FormPageLayout>
    );
}
