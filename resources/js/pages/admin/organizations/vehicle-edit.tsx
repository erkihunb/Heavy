import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { VehicleForm } from '@/components/vehicle-form';
import type { VehicleFormData } from '@/components/vehicle-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show } from '@/routes/admin/organizations';
import { update } from '@/routes/admin/organizations/vehicles';
import type { PlateRegionOption, VehicleType } from '@/types';
import type { AdminOrganization } from './index';

type Props = {
    organization: AdminOrganization;
    vehicle: {
        id: string;
        plate: string;
        vehicle_type_id: string;
        owner_name: string;
    };
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
};

/** Correct a vehicle in an organization's register: its plate, type and owner. */
export default function OrganizationVehicleEdit({
    organization,
    vehicle,
    vehicleTypes,
    plateRegions,
}: Props) {
    const { t } = useTranslation();
    const back = show.url({ organization: organization.id });

    const form = useForm<VehicleFormData>({
        plate: vehicle.plate,
        vehicle_type_id: vehicle.vehicle_type_id,
        owner_name: vehicle.owner_name,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(
            update.url({ organization: organization.id, vehicle: vehicle.id }),
            { preserveScroll: true },
        );
    };

    return (
        <FormPageLayout
            title={t('vehicles.correct_title')}
            description={organization.name_am}
            backHref={back}
            breadcrumbs={[
                { label: t('organizations.title'), href: index().url },
                { label: organization.name_am, href: back },
                { label: t('vehicles.correct_title') },
            ]}
        >
            <VehicleForm
                form={form}
                onSubmit={submit}
                vehicleTypes={vehicleTypes}
                plateRegions={plateRegions}
                cancelHref={back}
            />
        </FormPageLayout>
    );
}
