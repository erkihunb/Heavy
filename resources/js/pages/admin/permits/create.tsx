import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import type { OrganizationMatch } from '@/components/organization-lookup';
import { ApplicantStep } from '@/components/permit-intake/applicant-step';
import { IntakeButtons } from '@/components/permit-intake/buttons';
import {
    MORE_FIELDS,
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
import { useFeeEstimate } from '@/components/permit-intake/use-fee-estimate';
import { VehicleStep } from '@/components/permit-intake/vehicle-step';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, store } from '@/routes/admin/permits';
import type { PlateRegionOption, VehicleType } from '@/types';
import type { TranslationKey } from '@/types/translations';

type Props = {
    /** Supported languages: code => name written in that language. */
    locales: Record<string, string>;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    limits: { max_trailers: number; max_duration_days: number };
};

/**
 * The office's application form, in the order of the Authority's form:
 * the applicant, the vehicle and the permit asked for.
 * Saved together — as a draft, or submitted for review. There is no payment
 * here: the fee is paid once the permit is approved, and the receipt is
 * recorded then (by the applicant, the Registrar or the cashier). A
 * sticky panel shows how far the form is and keeps the buttons in reach.
 * The request is for a new organization — registered with it, and the
 * permit part can be switched off to register only the person — or for one
 * already on record, found with the lookup.
 */
export default function ApplicantsCreate({
    locales,
    vehicleTypes,
    plateRegions,
    limits,
}: Props) {
    const { t, locale } = useTranslation();
    const form = useForm<IntakeFormData>({
        applicant_id: '',
        contact_email: '',
        contact_name: '',
        phone: '',
        name_am: '',
        tin: '',
        name_en: '',
        email: '',
        address: '',
        locale,
        apply: true,
        submit: true,
        owner_name: '',
        vehicle_type_id: '',
        type_detail: '',
        plate: '',
        trailer_plate: '',
        permit_type: '',
        reason: '',
        valid_from: '',
        valid_to: '',
        any_time: true,
        move_from: '',
        move_to: '',
        letter_number: '',
        letter_date: '',
        letter_file: null,
    });
    // Errors for the vehicles arrive under keys such as "vehicles.1.plate".
    const errors = form.errors as Partial<Record<string, string>>;
    const errorFields = Object.keys(errors);
    const { data } = form;
    const [moreOpen, setMoreOpen] = useState(false);
    const [existingMode, setExistingMode] = useState(false);
    const [existing, setExisting] = useState<OrganizationMatch | null>(null);
    const showMore =
        moreOpen || MORE_FIELDS.some((field) => errors[field] !== undefined);

    const typeCode = vehicleTypes.find(
        (type) => String(type.id) === data.vehicle_type_id,
    )?.code;
    const trailerType = vehicleTypes.find((type) => type.code === TRAILER_TYPE);
    const canTow = limits.max_trailers > 0 && trailerType !== undefined;
    const estimate = useFeeEstimate(
        data.permit_type,
        data.vehicle_type_id,
        data.valid_from,
        data.valid_to,
    );

    const showStep = (step: StepKey) =>
        document.getElementById(`step-${step}`)?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });

    const chooseMode = (useExisting: boolean) => {
        setExistingMode(useExisting);
        setExisting(null);
        form.clearErrors('applicant_id', 'apply');
        // A request for an organization on record always comes with the request.
        form.setData((current) => ({
            ...current,
            applicant_id: '',
            apply: useExisting ? true : current.apply,
        }));
    };

    const chooseExisting = (match: OrganizationMatch | null) => {
        setExisting(match);
        form.clearErrors('applicant_id');
        form.setData('applicant_id', match === null ? '' : String(match.id));
    };

    const save = (submitNow: boolean) => (event?: FormEvent) => {
        event?.preventDefault();

        if (existingMode && existing === null) {
            form.setError(
                'applicant_id',
                t('applicants.intake.existing_required'),
            );
            showStep('applicant');

            return;
        }

        form.transform((current) => {
            const {
                owner_name,
                vehicle_type_id,
                type_detail,
                plate,
                trailer_plate,
                ...rest
            } = current;
            const vehicles = [
                {
                    plate,
                    vehicle_type_id,
                    owner_name,
                    type_detail: typeCode === OTHER_TYPE ? type_detail : '',
                },
                // The trailer belongs to the same owner and is registered as a trailer.
                ...(canTow && trailer_plate.trim() !== ''
                    ? [
                          {
                              plate: trailer_plate,
                              vehicle_type_id: String(trailerType.id),
                              owner_name,
                              type_detail: '',
                          },
                      ]
                    : []),
            ];

            return {
                ...rest,
                applicant_id: existingMode ? rest.applicant_id : '',
                vehicles,
                submit: submitNow,
            };
        });
        form.post(store.url(), {
            // The scanned letter travels as a file.
            forceFormData: true,
            preserveScroll: true,
            onError: (failed) => {
                // Bring the first wrong part into view.
                const first = (Object.keys(STEP_FIELDS) as StepKey[]).find(
                    (step) => Object.keys(failed).some(STEP_FIELDS[step]),
                );

                if (first) {
                    showStep(first);
                }
            },
        });
    };

    const stateOf = (
        step: StepKey,
        complete: boolean,
        optional = false,
    ): StepState =>
        errorFields.some(STEP_FIELDS[step])
            ? 'error'
            : complete
              ? 'done'
              : optional
                ? 'optional'
                : 'todo';

    const states: Record<StepKey, StepState> = {
        applicant: stateOf(
            'applicant',
            existingMode
                ? existing !== null
                : filled(
                      data.contact_email,
                      data.contact_name,
                      data.phone,
                      data.name_am,
                      data.tin,
                  ),
        ),
        vehicle: stateOf(
            'vehicle',
            filled(data.owner_name, data.vehicle_type_id, data.plate) &&
                (typeCode !== OTHER_TYPE || filled(data.type_detail)),
        ),
        permit: stateOf(
            'permit',
            filled(
                data.permit_type,
                data.valid_from,
                data.valid_to,
                data.reason,
                data.letter_number,
                data.letter_date,
            ) && data.letter_file !== null,
        ),
    };

    const steps: { key: StepKey; title: TranslationKey }[] = [
        {
            key: 'applicant',
            title: 'applicants.intake.sections.applicant.title',
        },
        ...(data.apply
            ? ([
                  {
                      key: 'vehicle',
                      title: 'applicants.intake.sections.vehicle.title',
                  },
                  {
                      key: 'permit',
                      title: 'applicants.intake.sections.permit.title',
                  },
              ] as { key: StepKey; title: TranslationKey }[])
            : []),
    ];
    return (
        <FormPageLayout
            title={t('register.add_new')}
            description={t('applicants.create_intro')}
            backHref={index.url()}
            width="full"
        >
            {/* The layout follows the room the form has, not the window: the sidebar takes its share. */}
            <div className="@container/intake">
                <form
                    onSubmit={save(data.apply)}
                    noValidate
                    className="grid items-start gap-6 @5xl/intake:grid-cols-[minmax(0,1fr)_20rem]"
                >
                    <div className="@container flex min-w-0 flex-col gap-6">
                        <ApplicantStep
                            form={form}
                            state={states.applicant}
                            locales={locales}
                            existingMode={existingMode}
                            existing={existing}
                            chooseMode={chooseMode}
                            chooseExisting={chooseExisting}
                            showMore={showMore}
                            setMoreOpen={setMoreOpen}
                        />

                        {data.apply && (
                            <>
                                <VehicleStep
                                    form={form}
                                    state={states.vehicle}
                                    vehicleTypes={vehicleTypes}
                                    plateRegions={plateRegions}
                                    existingMode={existingMode}
                                    typeCode={typeCode}
                                    canTow={canTow}
                                />
                                <PermitStep
                                    form={form}
                                    state={states.permit}
                                    maxDurationDays={limits.max_duration_days}
                                    estimate={estimate}
                                />
                            </>
                        )}

                        <div className="@5xl/intake:hidden">
                            <IntakeButtons
                                form={form}
                                stacked={false}
                                save={save}
                            />
                        </div>
                    </div>

                    <ProgressPanel
                        form={form}
                        steps={steps}
                        states={states}
                        existingMode={existingMode}
                        save={save}
                    />
                </form>
            </div>
        </FormPageLayout>
    );
}
