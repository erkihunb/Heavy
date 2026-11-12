import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { PermitForm } from '@/components/permit-form';
import type { PermitFormData, PermitLimits } from '@/components/permit-form';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { feeEstimate, index, show } from '@/routes/admin/applicants';
import { create, store } from '@/routes/admin/applicants/permits';
import type { BreadcrumbItem, Vehicle } from '@/types';
import type { WalkInApplicant } from '@/types/admin';

type Props = {
    applicant: WalkInApplicant;
    vehicles: Vehicle[];
    limits: PermitLimits;
};

/** A Registrar starts an application for a walk-in applicant. */
export default function WalkInPermitCreate({
    applicant,
    vehicles,
    limits,
}: Props) {
    const { t } = useTranslation();

    const form = useForm<PermitFormData>({
        permit_type: '',
        primary_vehicle_id: '',
        trailer_vehicle_ids: [],
        valid_from: '',
        valid_to: '',
        reason: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(applicant.id), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
        { title: applicant.organization, href: show(applicant.id) },
        { title: t('permits.create_title'), href: create(applicant.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.create_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('permits.create_title')}
                    description={t('applicants.for', {
                        organization: applicant.organization,
                    })}
                />
                <PermitForm
                    form={form}
                    onSubmit={submit}
                    vehicles={vehicles}
                    limits={limits}
                    cancelHref={show.url(applicant.id)}
                    estimateUrl={feeEstimate.url(applicant.id)}
                />
            </div>
        </AppLayout>
    );
}
