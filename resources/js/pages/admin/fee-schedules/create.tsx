import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FeeScheduleForm } from '@/components/fee-schedule-form';
import type { FeeScheduleFormData } from '@/components/fee-schedule-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, store } from '@/routes/admin/fee-schedules';
import type { VehicleType } from '@/types';

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

    return (
        <FormPageLayout title={t('fees.create_title')} backHref={index.url()}>
            <FeeScheduleForm
                form={form}
                onSubmit={submit}
                vehicleTypes={vehicleTypes}
                cancelHref={index.url()}
            />
        </FormPageLayout>
    );
}
