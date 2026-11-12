import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { VehicleForm } from '@/components/vehicle-form';
import type { VehicleFormData } from '@/components/vehicle-form';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show } from '@/routes/admin/applicants';
import { create, store } from '@/routes/admin/applicants/vehicles';
import type { BreadcrumbItem, PlateRegionOption, VehicleType } from '@/types';
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

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
        { title: applicant.organization, href: show(applicant.id) },
        { title: t('vehicles.register_title'), href: create(applicant.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('vehicles.register_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('vehicles.register_title')}
                    description={t('applicants.for', {
                        organization: applicant.organization,
                    })}
                />
                <VehicleForm
                    form={form}
                    onSubmit={submit}
                    vehicleTypes={vehicleTypes}
                    plateRegions={plateRegions}
                />
            </div>
        </AppLayout>
    );
}
