import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PermitForm } from '@/components/permit-form';
import type { PermitFormData, PermitLimits } from '@/components/permit-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { feeEstimate, index, show, update } from '@/routes/admin/permits';
import { create as createVehicle } from '@/routes/admin/permits/vehicles';
import type { Permit, Vehicle } from '@/types';
import type { WalkInApplicant } from '@/types/admin';

type Props = {
    applicant: WalkInApplicant;
    permit: Permit;
    vehicles: Vehicle[];
    limits: PermitLimits;
};

/** The Registrar corrects a request taken at the office: a draft, or one sent back for changes. */
export default function PermitEdit({
    applicant,
    permit,
    vehicles,
    limits,
}: Props) {
    const { t } = useTranslation();
    const permitVehicles = permit.vehicles ?? [];
    const route = permit.id;

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
        any_time: permit.hours === null,
        move_from: permit.hours?.from ?? '',
        move_to: permit.hours?.to ?? '',
        letter_number: permit.letter?.number ?? '',
        letter_date: permit.letter?.date?.gregorian ?? '',
        letter_file: null,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(update.url(route), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <FormPageLayout
            title={t('permits.edit_title')}
            description={t('applicants.for', {
                organization: applicant.organization,
            })}
            backHref={show.url(route)}
            breadcrumbs={[
                { label: t('register.title'), href: index().url },
                {
                    label: permit.permit_number ?? t('permits.show_title'),
                    href: show.url(route),
                },
                { label: t('permits.edit_title') },
            ]}
        >
            <PermitForm
                form={form}
                onSubmit={submit}
                vehicles={vehicles}
                limits={limits}
                cancelHref={show.url(route)}
                estimateUrl={feeEstimate.url(route)}
                letter={{ hasStoredScan: Boolean(permit.letter?.url) }}
                addVehicleHref={createVehicle.url(route)}
            />
        </FormPageLayout>
    );
}
