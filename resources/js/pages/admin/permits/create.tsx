import { useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { useFeeRates } from '@/components/fee-rates';
import type { OrganizationMatch } from '@/components/organization-lookup';
import { ApplicantStep } from '@/components/permit-intake/applicant-step';
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
import {
    plateKey,
    todayIso,
    useFeeEstimate,
    useKnownOrganization,
    useLiveChecks,
    useOrganizationVehicles,
} from '@/components/permit-intake/smart';
import { VehicleStep } from '@/components/permit-intake/vehicle-step';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, intakeRates, store } from '@/routes/admin/permits';
import type { PlateRegionOption, VehicleType } from '@/types';
import type { TranslationKey } from '@/types/translations';

type Props = {
    /** Supported languages: code => name written in that language. */
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    limits: {
        max_trailers: number;
        max_duration_days: number;
        max_letter_vehicles: number;
    };
};

/**
 * The office's application form, in the order of the Authority's form:
 * the applicant, the vehicle and the permit asked for.
 * Saved together and sent for a decision in one step (no drafts). There is no payment
 * here: the fee is paid once the permit is approved, and the receipt is
 * recorded then (by the applicant, the Registrar or the cashier). A
 * sticky panel shows how far the form is and keeps the buttons in reach.
 * The request is for a new organization — registered with it, and the
 * permit part can be switched off to register only the person — or for one
 * already on record, found with the lookup.
 */
export default function ApplicantsCreate({
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
        locale,
        apply: true,
        submit: true,
        vehicle_type_id: '',
        type_detail: '',
        plate: '',
        trailer_plate: '',
        owner_name: '',
        more: [],
        permit_type: '',
        reason: '',
        route: '',
        fee_schedule_id: '',
        valid_from: todayIso(),
        any_time: true,
        move_from: '',
        move_to: '',
        letter_number: '',
        // The letter is usually registered the day it comes in.
        letter_date: todayIso(),
        letter_file: null,
    });
    const [existingMode, setExistingMode] = useState(false);
    const [existing, setExisting] = useState<OrganizationMatch | null>(null);
    // Mistakes seen while typing, shown with the server's (which win).
    const live = useLiveChecks(form.data, existingMode);
    const smartForm = useMemo(
        () => ({ ...form, errors: { ...live, ...form.errors } }),
        [form, live],
    ) as typeof form;
    // Errors for the vehicles arrive under keys such as "vehicles.1.plate".
    const errors = smartForm.errors as Partial<Record<string, string>>;
    const errorFields = Object.keys(errors);
    const { data } = form;

    const typeCode = vehicleTypes.find(
        (type) => String(type.id) === data.vehicle_type_id,
    )?.code;
    const trailerType = vehicleTypes.find((type) => type.code === TRAILER_TYPE);
    const canTow = limits.max_trailers > 0 && trailerType !== undefined;
    // The rates offered for the permit type and the vehicle's type.
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
    // An organization on record that the new one being typed already is.
    const known = useKnownOrganization(data, existingMode);
    // The chosen organization's vehicles: a known plate brings its type.
    const knownVehicles = useOrganizationVehicles(
        existingMode ? existing : null,
    );

    useEffect(() => {
        if (knownVehicles.size === 0) {
            return;
        }

        const typeOf = (plate: string) => {
            const key = plateKey(plate);

            return key === null ? undefined : knownVehicles.get(key);
        };
        const first =
            data.vehicle_type_id === '' ? typeOf(data.plate) : undefined;
        const others = data.more.map((row) =>
            row.vehicle_type_id === '' ? typeOf(row.plate) : undefined,
        );

        if (first !== undefined || others.some((type) => type !== undefined)) {
            form.setData((current) => ({
                ...current,
                vehicle_type_id: first ?? current.vehicle_type_id,
                more: current.more.map((row, index) =>
                    others[index] === undefined
                        ? row
                        : { ...row, vehicle_type_id: others[index] },
                ),
            }));
        }
        // The form's setter is stable; the plates and types are what matter.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [knownVehicles, data.plate, data.vehicle_type_id, data.more]);

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

    // The letter's fee: every vehicle at its own type's fee for the rate's days.
    const days =
        rates.state === 'ready'
            ? (rates.rates.find(
                  (rate) => String(rate.id) === data.fee_schedule_id,
              )?.days ?? null)
            : null;
    const fee = useFeeEstimate(data, days);
    const typeName = (id: string) =>
        vehicleTypes.find((type) => String(type.id) === id)?.name ?? id;

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

    const save = (event?: FormEvent) => {
        event?.preventDefault();

        if (existingMode && existing === null) {
            form.setError(
                'applicant_id',
                t('applicants.intake.existing_required'),
            );
            showStep('applicant');

            return;
        }

        // A mistake already visible is fixed before anything is sent.
        const wrong = (Object.keys(STEP_FIELDS) as StepKey[]).find((step) =>
            Object.keys(live).some(STEP_FIELDS[step]),
        );

        if (wrong !== undefined) {
            showStep(wrong);

            return;
        }

        form.transform((current) => {
            const {
                vehicle_type_id,
                type_detail,
                plate,
                trailer_plate,
                owner_name,
                more,
                ...rest
            } = current;
            const vehicles = [
                {
                    plate,
                    vehicle_type_id,
                    type_detail: typeCode === OTHER_TYPE ? type_detail : '',
                    owner_name,
                },
                // The trailer is registered as a trailer of the same organization.
                ...(canTow && trailer_plate.trim() !== ''
                    ? [
                          {
                              plate: trailer_plate,
                              vehicle_type_id: String(trailerType.id),
                              type_detail: '',
                              owner_name,
                          },
                      ]
                    : []),
            ];

            // The other vehicles of the letter, each with its trailer.
            const moreVehicles = more.map((row) => {
                const code = vehicleTypes.find(
                    (type) => String(type.id) === row.vehicle_type_id,
                )?.code;

                return {
                    vehicles: [
                        {
                            plate: row.plate,
                            vehicle_type_id: row.vehicle_type_id,
                            type_detail:
                                code === OTHER_TYPE ? row.type_detail : '',
                            owner_name: row.owner_name,
                        },
                        ...(canTow && row.trailer_plate.trim() !== ''
                            ? [
                                  {
                                      plate: row.trailer_plate,
                                      vehicle_type_id: String(trailerType.id),
                                      type_detail: '',
                                      owner_name: row.owner_name,
                                  },
                              ]
                            : []),
                    ],
                };
            });

            return {
                ...rest,
                applicant_id: existingMode ? rest.applicant_id : '',
                vehicles,
                more: moreVehicles,
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
            backHref={index.url()}
            width="full"
        >
            {/* The layout follows the room the form has, not the window: the sidebar takes its share. */}
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
                            existingMode={existingMode}
                            existing={existing}
                            chooseMode={chooseMode}
                            chooseExisting={chooseExisting}
                            known={known}
                        />

                        {data.apply && (
                            <>
                                <VehicleStep
                                    form={smartForm}
                                    state={states.vehicle}
                                    vehicleTypes={vehicleTypes}
                                    plateRegions={plateRegions}
                                    typeCode={typeCode}
                                    canTow={canTow}
                                    maxLetterVehicles={
                                        limits.max_letter_vehicles
                                    }
                                />
                                <PermitStep
                                    form={smartForm}
                                    state={states.permit}
                                    rates={rates}
                                    letter={{ ...fee, typeName }}
                                />
                            </>
                        )}

                        <div className="@5xl/intake:hidden">
                            <IntakeButtons form={smartForm} stacked={false} />
                        </div>
                    </div>

                    <ProgressPanel
                        form={smartForm}
                        steps={steps}
                        states={states}
                        existingMode={existingMode}
                        fee={fee}
                        typeName={typeName}
                    />
                </form>
            </div>
        </FormPageLayout>
    );
}
