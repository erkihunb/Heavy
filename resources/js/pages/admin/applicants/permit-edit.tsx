import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { PermitForm } from '@/components/permit-form';
import type { PermitFormData, PermitLimits } from '@/components/permit-form';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { feeEstimate, index, show } from '@/routes/admin/applicants';
import {
    edit,
    show as showPermit,
    update,
} from '@/routes/admin/applicants/permits';
import type { BreadcrumbItem, Permit, Vehicle } from '@/types';
import type { WalkInApplicant } from '@/types/admin';

type Props = {
    applicant: WalkInApplicant;
    permit: Permit;
    vehicles: Vehicle[];
    limits: PermitLimits;
};

/** A Registrar edits a walk-in applicant's draft or returned application. */
export default function WalkInPermitEdit({
    applicant,
    permit,
    vehicles,
    limits,
}: Props) {
    const { t } = useTranslation();
    const permitVehicles = permit.vehicles ?? [];
    const route = { applicant: applicant.id, permit: permit.id };

    const form = useForm<PermitFormData>({
        permit_type: permit.permit_type,
        primary_vehicle_id:
            permitVehicles.find((vehicle) => vehicle.role === 'primary')?.id ??
            '',
        trailer_vehicle_ids: permitVehicles
            .filter((vehicle) => vehicle.role === 'trailer')
            .map((vehicle) => vehicle.id),
        valid_from: permit.valid_from.gregorian,
        valid_to: permit.valid_to.gregorian,
        reason: permit.reason,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url(route), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
        { title: applicant.organization, href: show(applicant.id) },
        { title: t('permits.show_title'), href: showPermit(route) },
        { title: t('permits.edit_title'), href: edit(route) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.edit_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('permits.edit_title')}
                    description={t('applicants.for', {
                        organization: applicant.organization,
                    })}
                />
                <PermitForm
                    form={form}
                    onSubmit={submit}
                    vehicles={vehicles}
                    limits={limits}
                    cancelHref={showPermit.url(route)}
                    estimateUrl={feeEstimate.url(applicant.id)}
                />
            </div>
        </AppLayout>
    );
}
