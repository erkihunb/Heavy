import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { VehicleForm } from '@/components/vehicle-form';
import type { VehicleFormData } from '@/components/vehicle-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { edit, index, show } from '@/routes/admin/permits';
import { store } from '@/routes/admin/permits/vehicles';
import type { PlateRegionOption, VehicleType } from '@/types';
import type { WalkInApplicant } from '@/types/admin';

type Props = {
    applicant: WalkInApplicant;
    /** The request the vehicle is for; back to correcting it afterwards. */
    permit: { id: string; permit_number: string | null };
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
};

/** The Registrar adds a vehicle a request needs to its organisation's register. */
export default function PermitVehicleCreate({
    applicant,
    permit,
    vehicleTypes,
    plateRegions,
}: Props) {
    const { t } = useTranslation();

    const form = useForm<VehicleFormData>({
        plate: '',
        vehicle_type_id: '',
        owner_name: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(permit.id), { preserveScroll: true });
    };

    return (
        <FormPageLayout
            title={t('vehicles.register_title')}
            description={t('applicants.for', {
                organization: applicant.organization,
            })}
            backHref={edit.url(permit.id)}
            breadcrumbs={[
                { label: t('register.title'), href: index().url },
                {
                    label: permit.permit_number ?? t('permits.show_title'),
                    href: show.url(permit.id),
                },
                { label: t('permits.edit_title'), href: edit.url(permit.id) },
                { label: t('vehicles.register_title') },
            ]}
        >
            <VehicleForm
                form={form}
                onSubmit={submit}
                vehicleTypes={vehicleTypes}
                plateRegions={plateRegions}
                cancelHref={edit.url(permit.id)}
            />
        </FormPageLayout>
    );
}
