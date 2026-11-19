import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import type { FormEvent } from 'react';
import { useFeeRates } from '@/components/fee-rates';
import { ApplicantStep } from '@/components/permit-intake/applicant-step';
import type { LockedApplicant } from '@/components/permit-intake/applicant-step';
import { IntakeButtons } from '@/components/permit-intake/buttons';
import {
    OTHER_TYPE,
    STEP_FIELDS,
    TRAILER_TYPE,
    filled,
} from '@/components/permit-intake/form';
import type {
    IntakeFormData,
    StepKey,
    StepState,
} from '@/components/permit-intake/form';
import { PermitStep } from '@/components/permit-intake/permit-step';
import { ProgressPanel } from '@/components/permit-intake/progress-panel';
import { useLiveChecks } from '@/components/permit-intake/smart';
import { VehicleStep } from '@/components/permit-intake/vehicle-step';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, intakeRates, show, update } from '@/routes/admin/permits';
import type { Permit, PlateRegionOption, VehicleType } from '@/types';
import type { WalkInApplicant } from '@/types/admin';
import type { TranslationKey } from '@/types/translations';

type Props = {
    applicant: WalkInApplicant;
    /** The organization the request was registered for, shown as it is. */
    organization: LockedApplicant;
    permit: Permit;
    /** The request's vehicle and trailer, as the create form has them. */
    intake: {
        vehicle_type_id: string;
        type_detail: string;
        plate: string;
        owner_name: string;
        trailer_plate: string;
    };
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    limits: { max_trailers: number; max_duration_days: number };
    /** The rate offered today whose days are the request's own, if there is one. */
    feeScheduleId: number | null;
    /** A scan of the letter is already kept (the permit's own, or its request letter's). */
    hasStoredScan: boolean;
};

/**
 * The Registrar corrects a request taken at the office (a draft, or one sent
 * back for changes) on the same form it was registered with, filled in: the
 * applicant as registered, the vehicle and its trailer, and the permit. Saving
 * keeps the corrections; it is sent again from the request's page.
 */
export default function PermitEdit({
    applicant,
    organization,
    permit,
    intake,
    vehicleTypes,
    plateRegions,
    limits,
    feeScheduleId,
    hasStoredScan,
}: Props) {
    const { t, locale } = useTranslation();
    const route = permit.id;

    const form = useForm<IntakeFormData>({
        applicant_id: String(applicant.id),
        contact_email: organization.contact_email,
        contact_name: organization.contact_name,
        phone: organization.phone ?? '',
        name_am: organization.name,
        tin: organization.tin ?? '',
        locale,
        apply: true,
        submit: false,
        vehicle_type_id: intake.vehicle_type_id,
        type_detail: intake.type_detail,
        plate: intake.plate,
        trailer_plate: intake.trailer_plate,
        owner_name: intake.owner_name,
        more: [],
        permit_type: permit.permit_type,
        reason: permit.reason,
        route: permit.route ?? '',
        fee_schedule_id: feeScheduleId === null ? '' : String(feeScheduleId),
        valid_from: permit.valid_from.gregorian,
        any_time: permit.hours === null,
        move_from: permit.hours?.from ?? '',
        move_to: permit.hours?.to ?? '',
        letter_number: permit.letter?.number ?? '',
        letter_date: permit.letter?.date?.gregorian ?? '',
        letter_file: null,
    });

    // Mistakes seen while typing, shown with the server's (which win).
    const live = useLiveChecks(form.data, true);
    const smartForm = useMemo(
        () => ({ ...form, errors: { ...live, ...form.errors } }),
        [form, live],
    ) as typeof form;
    const errors = smartForm.errors as Partial<Record<string, string>>;
    const errorFields = Object.keys(errors);
    const { data } = form;

    const typeCode = vehicleTypes.find(
        (type) => String(type.id) === data.vehicle_type_id,
    )?.code;
    const trailerType = vehicleTypes.find((type) => type.code === TRAILER_TYPE);
    const canTow = limits.max_trailers > 0 && trailerType !== undefined;
    const rates = useFeeRates(
        filled(data.permit_type, data.vehicle_type_id)
            ? intakeRates.url()
            : null,
        {
            permit_type: data.permit_type,
            vehicle_type_id: data.vehicle_type_id,
        },
        data.valid_from,
    );

    // When the schedule offers one rate only, it is the one.
    const onlyRate =
        rates.state === 'ready' && rates.rates.length === 1
            ? String(rates.rates[0].id)
            : null;

    useEffect(() => {
        if (onlyRate !== null && data.fee_schedule_id === '') {
            form.setData('fee_schedule_id', onlyRate);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [onlyRate, data.fee_schedule_id]);

    const showStep = (step: StepKey) =>
        document.getElementById(`step-${step}`)?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });

    const save = (event?: FormEvent) => {
        event?.preventDefault();

        const wrong = (['vehicle', 'permit'] as StepKey[]).find((step) =>
            Object.keys(live).some(STEP_FIELDS[step]),
        );

        if (wrong !== undefined) {
            showStep(wrong);

            return;
        }

        form.transform((current) => ({
            _method: 'put',
            permit_type: current.permit_type,
            reason: current.reason,
            route: current.route,
            fee_schedule_id: current.fee_schedule_id,
            valid_from: current.valid_from,
            any_time: current.any_time,
            move_from: current.move_from,
            move_to: current.move_to,
            letter_number: current.letter_number,
            letter_date: current.letter_date,
            letter_file: current.letter_file,
            vehicles: [
                {
                    plate: current.plate,
                    vehicle_type_id: current.vehicle_type_id,
                    type_detail:
                        typeCode === OTHER_TYPE ? current.type_detail : '',
                    owner_name: current.owner_name,
                },
                // The trailer goes with the vehicle, owned by the same.
                ...(canTow && current.trailer_plate.trim() !== ''
                    ? [
                          {
                              plate: current.trailer_plate,
                              vehicle_type_id: String(trailerType.id),
                              type_detail: '',
                              owner_name: current.owner_name,
                          },
                      ]
                    : []),
            ],
        }));
        form.post(update.url(route), {
            forceFormData: true,
            preserveScroll: true,
            onError: (failed) => {
                const first = (Object.keys(STEP_FIELDS) as StepKey[]).find(
                    (step) => Object.keys(failed).some(STEP_FIELDS[step]),
                );

                if (first) {
                    showStep(first);
                }
            },
        });
    };

    const stateOf = (step: StepKey, complete: boolean): StepState =>
        errorFields.some(STEP_FIELDS[step])
            ? 'error'
            : complete
              ? 'done'
              : 'todo';

    const states: Record<StepKey, StepState> = {
        applicant: 'done',
        vehicle: stateOf(
            'vehicle',
            filled(data.vehicle_type_id, data.plate) &&
                (typeCode !== OTHER_TYPE || filled(data.type_detail)),
        ),
        permit: stateOf(
            'permit',
            filled(
                data.permit_type,
                data.fee_schedule_id,
                data.valid_from,
                data.reason,
                data.letter_number,
                data.letter_date,
            ) &&
                (hasStoredScan || data.letter_file !== null),
        ),
    };

    const steps: { key: StepKey; title: TranslationKey }[] = [
        {
            key: 'applicant',
            title: 'applicants.intake.sections.applicant.title',
        },
        { key: 'vehicle', title: 'applicants.intake.sections.vehicle.title' },
        { key: 'permit', title: 'applicants.intake.sections.permit.title' },
    ];
    const buttons = {
        submitLabel: t('permits.correction.save'),
        cancelHref: show.url(route),
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
            width="full"
        >
            <div className="@container/intake">
                <form
                    onSubmit={save}
                    noValidate
                    className="grid items-start gap-6 @5xl/intake:grid-cols-[minmax(0,1fr)_20rem]"
                >
                    <div className="@container flex min-w-0 flex-col gap-6">
                        <ApplicantStep
                            form={smartForm}
                            state={states.applicant}
                            existingMode
                            existing={null}
                            chooseMode={() => undefined}
                            chooseExisting={() => undefined}
                            locked={organization}
                        />
                        <VehicleStep
                            form={smartForm}
                            state={states.vehicle}
                            vehicleTypes={vehicleTypes}
                            plateRegions={plateRegions}
                            typeCode={typeCode}
                            canTow={canTow}
                            // The letter's other vehicles are requests of their own.
                            maxLetterVehicles={1}
                        />
                        <PermitStep
                            form={smartForm}
                            state={states.permit}
                            rates={rates}
                            hasStoredScan={hasStoredScan}
                        />

                        <div className="@5xl/intake:hidden">
                            <IntakeButtons
                                form={smartForm}
                                stacked={false}
                                {...buttons}
                            />
                        </div>
                    </div>

                    <ProgressPanel
                        form={smartForm}
                        steps={steps}
                        states={states}
                        existingMode
                        buttons={buttons}
                    />
                </form>
            </div>
        </FormPageLayout>
    );
}
