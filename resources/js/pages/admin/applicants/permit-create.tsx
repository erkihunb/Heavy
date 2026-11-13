import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PermitForm } from '@/components/permit-form';
import type { PermitFormData, PermitLimits } from '@/components/permit-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { feeEstimate, index, show } from '@/routes/admin/applicants';
import { store } from '@/routes/admin/applicants/permits';
import type { Vehicle } from '@/types';
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
        any_time: true,
        move_from: '',
        move_to: '',
        letter_number: '',
        letter_date: '',
        letter_file: null,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(applicant.id), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <FormPageLayout
            title={t('permits.create_title')}
            description={t('applicants.for', {
                organization: applicant.organization,
            })}
            backHref={show.url(applicant.id)}
            breadcrumbs={[
                { label: t('applicants.title'), href: index().url },
                { label: applicant.organization, href: show.url(applicant.id) },
                { label: t('permits.create_title') },
            ]}
        >
            <PermitForm
                form={form}
                onSubmit={submit}
                vehicles={vehicles}
                limits={limits}
                cancelHref={show.url(applicant.id)}
                estimateUrl={feeEstimate.url(applicant.id)}
                letter={{ hasStoredScan: false }}
            />
        </FormPageLayout>
    );
}
