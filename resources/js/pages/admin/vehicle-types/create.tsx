import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { VehicleTypeForm } from '@/components/vehicle-type-form';
import type { VehicleTypeFormData } from '@/components/vehicle-type-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, store } from '@/routes/admin/vehicle-types';

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

    return (
        <FormPageLayout
            title={t('admin.vehicle_types.create_title')}
            backHref={index.url()}
        >
            <VehicleTypeForm
                form={form}
                onSubmit={submit}
                cancelHref={index.url()}
                codeLocked={false}
            />
        </FormPageLayout>
    );
}
