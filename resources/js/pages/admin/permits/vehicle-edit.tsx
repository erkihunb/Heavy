import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { VehicleForm } from '@/components/vehicle-form';
import type { VehicleFormData } from '@/components/vehicle-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { edit, index, show } from '@/routes/admin/permits';
import { update } from '@/routes/admin/permits/vehicles';
import type { PlateRegionOption, VehicleType } from '@/types';
import type { WalkInApplicant } from '@/types/admin';

type Props = {
    applicant: WalkInApplicant;
    /** The request the vehicle is on; back to correcting it afterwards. */
    permit: { id: string; permit_number: string | null };
    vehicle: {
        id: string;
        plate: string;
        vehicle_type_id: string;
        owner_name: string;
    };
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
};

/**
 * The Registrar corrects a vehicle of a draft or returned request: its plate,
 * type and owner (App\Http\Controllers\Admin\WalkInController::editVehicle).
 */
export default function PermitVehicleEdit({
    applicant,
    permit,
    vehicle,
    vehicleTypes,
    plateRegions,
}: Props) {
    const { t } = useTranslation();

    const form = useForm<VehicleFormData>({
        plate: vehicle.plate,
        vehicle_type_id: vehicle.vehicle_type_id,
        owner_name: vehicle.owner_name,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url({ permit: permit.id, vehicle: vehicle.id }), {
            preserveScroll: true,
        });
    };

    return (
        <FormPageLayout
            title={t('vehicles.correct_title')}
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
                { label: t('vehicles.correct_title') },
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
