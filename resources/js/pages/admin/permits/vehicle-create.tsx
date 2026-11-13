import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { VehicleForm } from '@/components/vehicle-form';
import type { VehicleFormData } from '@/components/vehicle-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show } from '@/routes/admin/applicants';
import { store } from '@/routes/admin/applicants/vehicles';
import type { PlateRegionOption, VehicleType } from '@/types';
import type { WalkInApplicant } from '@/types/admin';

type Props = {
    applicant: WalkInApplicant;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
};

/** A Registrar adds a vehicle to a walk-in applicant's register. */
export default function WalkInVehicleCreate({
    applicant,
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
        form.post(store.url(applicant.id), { preserveScroll: true });
    };

    return (
        <FormPageLayout
            title={t('vehicles.register_title')}
            description={t('applicants.for', {
                organization: applicant.organization,
            })}
            backHref={show(applicant.id).url}
            breadcrumbs={[
                { label: t('applicants.title'), href: index().url },
                { label: applicant.organization, href: show(applicant.id).url },
                { label: t('vehicles.register_title') },
            ]}
        >
            <VehicleForm
                form={form}
                onSubmit={submit}
                vehicleTypes={vehicleTypes}
                plateRegions={plateRegions}
                cancelHref={show(applicant.id).url}
            />
        </FormPageLayout>
    );
}
