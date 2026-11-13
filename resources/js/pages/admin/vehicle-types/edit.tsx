import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { VehicleTypeForm } from '@/components/vehicle-type-form';
import type { VehicleTypeFormData } from '@/components/vehicle-type-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, update } from '@/routes/admin/vehicle-types';
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

    return (
        <FormPageLayout
            title={t('admin.vehicle_types.edit_title')}
            backHref={index.url()}
        >
            <VehicleTypeForm
                form={form}
                onSubmit={submit}
                cancelHref={index.url()}
                codeLocked
            />
        </FormPageLayout>
    );
}
