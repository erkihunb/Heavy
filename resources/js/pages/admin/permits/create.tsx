import { Link, useForm } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    CircleAlert,
    MailCheck,
    Save,
    Send,
    UserPlus,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { LetterFields } from '@/components/letter-fields';
import type { LetterValue } from '@/components/letter-fields';
import { MovementHoursField } from '@/components/movement-hours-field';
import { OrganizationLookup } from '@/components/organization-lookup';
import type { OrganizationMatch } from '@/components/organization-lookup';
import { PlateInput } from '@/components/plate-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, intakeFee, store } from '@/routes/admin/permits';
import type { Money, PlateRegionOption, VehicleType } from '@/types';
import { PERMIT_TYPES } from '@/types/enums';
import type { TranslationKey } from '@/types/translations';

/** The vehicle type whose vehicles are described in words (App\Models\VehicleType::OTHER). */
const OTHER_TYPE = 'other';
/** The type a trailer plate is registered with (App\Models\VehicleType::TRAILER). */
const TRAILER_TYPE = 'trailer';

type Props = {
    /** Supported languages: code => name written in that language. */
    locales: Record<string, string>;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    limits: { max_trailers: number; max_duration_days: number };
};

type FormData = {
    // 1. The applicant: an organization on record, or a new one.
    applicant_id: string;
    contact_email: string;
    contact_name: string;
    phone: string;
    name_am: string;
    tin: string;
    // More about the organization (optional).
    name_en: string;
    email: string;
    address: string;
    locale: string;
    apply: boolean;
    submit: boolean;
    // 2. The vehicle, and its trailer if any.
    owner_name: string;
    vehicle_type_id: string;
    type_detail: string;
    plate: string;
    trailer_plate: string;
    // 3. The permit.
    permit_type: string;
    reason: string;
    valid_from: string;
    valid_to: string;
    any_time: boolean;
    move_from: string;
    move_to: string;
    // 4. The confirmation.
    confirmed: boolean;
} & LetterValue;

type StepKey = 'applicant' | 'vehicle' | 'permit' | 'confirm';

type StepState = 'done' | 'todo' | 'optional' | 'error';

const filled = (...values: string[]) =>
    values.every((value) => value.trim() !== '');

/** Which form fields belong to which step, to mark a step that has errors. */
const STEP_FIELDS: Record<StepKey, (field: string) => boolean> = {
    applicant: (field) =>
        [
            'applicant_id',
            'apply',
            'contact_name',
            'contact_email',
            'locale',
            'name_am',
            'name_en',
            'tin',
            'phone',
            'email',
            'address',
        ].includes(field),
    vehicle: (field) => field.startsWith('vehicles'),
    permit: (field) =>
        [
            'permit_type',
            'valid_from',
            'valid_to',
            'reason',
            'any_time',
            'move_from',
            'move_to',
            'letter_number',
            'letter_date',
            'letter_file',
        ].includes(field),
    confirm: (field) => field === 'confirmed',
};

/** Fields the organization's details hide under "more", to open them on an error. */
const MORE_FIELDS = ['name_en', 'email', 'address', 'locale'];

/**
 * One numbered part of the form (EUISIS form section): the number — a tick
 * once it is complete, a warning when something is wrong — the title, a
 * line on what it is for, and the fields.
 */
function Step({
    id,
    number,
    title,
    description,
    state,
    children,
}: {
    id: StepKey;
    number: number;
    title: string;
    description: string;
    state: StepState;
    children: ReactNode;
}) {
    return (
        <section
            id={`step-${id}`}
            aria-labelledby={`step-${id}-title`}
            className={cn(
                'scroll-mt-24 overflow-hidden rounded-card border bg-card',
                state === 'error' ? 'border-destructive/60' : 'border-border',
            )}
        >
            <header className="flex flex-wrap items-start gap-4 border-b border-border px-5 py-4 sm:px-6">
                <StepNumber number={number} state={state} />
                <div className="min-w-0 flex-1 basis-56">
                    <h2 id={`step-${id}-title`} className="text-section-title">
                        {title}
                    </h2>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
            </header>
            <div className="p-5 sm:p-6">{children}</div>
        </section>
    );
}

function StepNumber({ number, state }: { number: number; state: StepState }) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold tabular-nums',
                state === 'done' && 'bg-success text-success-foreground',
                state === 'error' && 'bg-destructive text-white',
                (state === 'todo' || state === 'optional') &&
                    'bg-primary/10 text-primary',
            )}
        >
            {state === 'done' ? (
                <Check className="size-4" strokeWidth={3} />
            ) : state === 'error' ? (
                <CircleAlert className="size-4" strokeWidth={2.5} />
            ) : (
                number
            )}
        </span>
    );
}

/**
 * What the request would cost by today's schedule, asked of the server once
 * the type, the vehicle type and both dates are chosen. Shown for the
 * applicant's information: the fee is paid only once the permit is approved.
 */
function useFeeEstimate(
    permitType: string,
    vehicleTypeId: string,
    validFrom: string,
    validTo: string,
): { days: number; fee: Money | null } | null {
    const [estimate, setEstimate] = useState<{
        key: string;
        days: number;
        fee: Money | null;
    } | null>(null);
    const complete =
        filled(permitType, vehicleTypeId, validFrom, validTo) &&
        validFrom <= validTo;
    const key = [permitType, vehicleTypeId, validFrom, validTo].join('|');

    useEffect(() => {
        if (!complete) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(
                intakeFee.url({
                    query: {
                        permit_type: permitType,
                        vehicle_type_id: vehicleTypeId,
                        valid_from: validFrom,
                        valid_to: validTo,
                    },
                }),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            )
                .then((response) => (response.ok ? response.json() : null))
                .then((body: { days: number; fee: Money | null } | null) =>
                    setEstimate(body === null ? null : { key, ...body }),
                )
                .catch(() => {
                    // Aborted or offline: the estimate only helps.
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [complete, key, permitType, vehicleTypeId, validFrom, validTo]);

    return complete && estimate?.key === key ? estimate : null;
}

/**
 * The office's application form, in the order of the Authority's form:
 * the applicant, the vehicle, the permit asked for and the confirmation.
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
    const form = useForm<FormData>({
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
        confirmed: false,
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
        confirm: stateOf('confirm', data.confirmed),
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
                  {
                      key: 'confirm',
                      title: 'applicants.intake.sections.confirm.title',
                  },
              ] as { key: StepKey; title: TranslationKey }[])
            : []),
    ];
    const required = steps;
    const completed = required.filter(
        (step) => states[step.key] === 'done',
    ).length;

    const text = (
        field:
            | 'contact_name'
            | 'name_am'
            | 'name_en'
            | 'address'
            | 'owner_name'
            | 'type_detail',
        label: string,
        extra: {
            lang?: string;
            maxLength?: number;
            autoComplete?: string;
            hint?: string;
            error?: string;
            translate?: 'no';
        } = {},
    ) => {
        const { hint, error, ...input } = extra;

        return (
            <FormField
                id={field}
                label={label}
                hint={hint}
                error={error ?? errors[field]}
            >
                {(control) => (
                    <Input
                        {...control}
                        {...input}
                        value={data[field]}
                        onChange={(event) =>
                            form.setData(field, event.target.value)
                        }
                    />
                )}
            </FormField>
        );
    };

    const buttons = (stacked: boolean) => (
        <div
            className={cn(
                'flex gap-2',
                stacked ? 'flex-col' : 'flex-col-reverse sm:flex-row',
            )}
        >
            {data.apply ? (
                <>
                    <Button type="submit" size="lg" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <Send aria-hidden="true" />
                        )}
                        {t('applicants.intake.submit')}
                    </Button>
                    <Button
                        type="button"
                        size="lg"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => save(false)()}
                    >
                        <Save aria-hidden="true" />
                        {t('applicants.intake.save_draft')}
                    </Button>
                </>
            ) : (
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <UserPlus aria-hidden="true" />
                    )}
                    {t('applicants.form.register_only')}
                </Button>
            )}
            <Button asChild variant="ghost" size={stacked ? 'default' : 'lg'}>
                <Link href={index()}>{t('applicants.intake.cancel')}</Link>
            </Button>
        </div>
    );

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
                        {/* 1. The applicant. */}
                        <Step
                            id="applicant"
                            number={1}
                            title={t(
                                'applicants.intake.sections.applicant.title',
                            )}
                            description={t(
                                'applicants.intake.sections.applicant.intro',
                            )}
                            state={states.applicant}
                        >
                            <fieldset className="mb-5">
                                <legend className="mb-2 text-sm font-medium">
                                    {t('applicants.intake.mode')}
                                </legend>
                                <div className="grid gap-3 @xl:grid-cols-2">
                                    {[false, true].map((option) => (
                                        <label
                                            key={String(option)}
                                            className={cn(
                                                'flex cursor-pointer items-start gap-3 rounded-card border p-4 transition-colors has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50',
                                                existingMode === option
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border hover:bg-muted',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="applicant_mode"
                                                checked={
                                                    existingMode === option
                                                }
                                                onChange={() =>
                                                    chooseMode(option)
                                                }
                                                className="mt-0.5 size-4 accent-primary"
                                            />
                                            <span className="grid gap-0.5">
                                                <span className="text-sm font-semibold">
                                                    {t(
                                                        option
                                                            ? 'applicants.intake.mode_existing'
                                                            : 'applicants.intake.mode_new',
                                                    )}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {t(
                                                        option
                                                            ? 'applicants.intake.mode_existing_hint'
                                                            : 'applicants.intake.mode_new_hint',
                                                    )}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </fieldset>

                            {existingMode ? (
                                <OrganizationLookup
                                    value={existing}
                                    onChange={chooseExisting}
                                    error={errors.applicant_id ?? errors.apply}
                                />
                            ) : (
                                <>
                                    <div className="grid gap-5 @2xl:grid-cols-2">
                                        <FormField
                                            id="contact_email"
                                            label={t(
                                                'applicants.intake.fields.email',
                                            )}
                                            hint={t(
                                                'applicants.fields.contact_email_hint',
                                            )}
                                            error={errors.contact_email}
                                        >
                                            {(control) => (
                                                <Input
                                                    {...control}
                                                    type="email"
                                                    autoComplete="off"
                                                    translate="no"
                                                    maxLength={255}
                                                    value={data.contact_email}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'contact_email',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormField>
                                        {text(
                                            'contact_name',
                                            t('applicants.intake.fields.name'),
                                            {
                                                maxLength: 255,
                                                autoComplete: 'off',
                                            },
                                        )}
                                        <FormField
                                            id="phone"
                                            label={t(
                                                'applicants.intake.fields.phone',
                                            )}
                                            hint={t(
                                                'organization.fields.phone_hint',
                                            )}
                                            error={errors.phone}
                                        >
                                            {(control) => (
                                                <Input
                                                    {...control}
                                                    type="tel"
                                                    maxLength={30}
                                                    className="tabular-nums"
                                                    value={data.phone}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'phone',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormField>
                                        {text(
                                            'name_am',
                                            t(
                                                'applicants.intake.fields.organization',
                                            ),
                                            { lang: 'am', maxLength: 255 },
                                        )}
                                        <FormField
                                            id="tin"
                                            label={t(
                                                'applicants.intake.fields.tin',
                                            )}
                                            hint={t(
                                                'organization.fields.tin_hint',
                                            )}
                                            error={errors.tin}
                                        >
                                            {(control) => (
                                                <Input
                                                    {...control}
                                                    inputMode="numeric"
                                                    className="tabular-nums"
                                                    value={data.tin}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'tin',
                                                            event.target.value
                                                                .replace(
                                                                    /\D/g,
                                                                    '',
                                                                )
                                                                .slice(0, 10),
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormField>
                                    </div>

                                    <div className="mt-5 rounded-card border border-border">
                                        <button
                                            type="button"
                                            aria-expanded={showMore}
                                            aria-controls="organization-more"
                                            onClick={() =>
                                                setMoreOpen(!showMore)
                                            }
                                            className="flex w-full items-center gap-3 rounded-card px-4 py-3 text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm font-semibold">
                                                    {t(
                                                        'applicants.intake.more',
                                                    )}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {t(
                                                        'applicants.intake.more_hint',
                                                    )}
                                                </span>
                                            </span>
                                            <ChevronDown
                                                aria-hidden="true"
                                                className={cn(
                                                    'size-4 shrink-0 transition-transform',
                                                    showMore && 'rotate-180',
                                                )}
                                            />
                                        </button>
                                        {showMore && (
                                            <div
                                                id="organization-more"
                                                className="grid gap-5 border-t border-border p-4 @2xl:grid-cols-2"
                                            >
                                                {text(
                                                    'name_en',
                                                    t(
                                                        'organization.fields.name_en',
                                                    ),
                                                    {
                                                        lang: 'en',
                                                        maxLength: 255,
                                                    },
                                                )}
                                                <FormField
                                                    id="email"
                                                    label={t(
                                                        'organization.fields.email',
                                                    )}
                                                    hint={t(
                                                        'applicants.intake.org_email_hint',
                                                    )}
                                                    error={errors.email}
                                                >
                                                    {(control) => (
                                                        <Input
                                                            {...control}
                                                            type="email"
                                                            translate="no"
                                                            maxLength={255}
                                                            value={data.email}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'email',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </FormField>
                                                {text(
                                                    'address',
                                                    t(
                                                        'organization.fields.address',
                                                    ),
                                                    { maxLength: 500 },
                                                )}
                                                <FormField
                                                    id="locale"
                                                    label={t(
                                                        'applicants.fields.locale',
                                                    )}
                                                    error={errors.locale}
                                                >
                                                    {(control) => (
                                                        <NativeSelect
                                                            {...control}
                                                            value={data.locale}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'locale',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        >
                                                            {Object.entries(
                                                                locales,
                                                            ).map(
                                                                ([
                                                                    code,
                                                                    name,
                                                                ]) => (
                                                                    <option
                                                                        key={
                                                                            code
                                                                        }
                                                                        value={
                                                                            code
                                                                        }
                                                                    >
                                                                        {name}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    )}
                                                </FormField>
                                            </div>
                                        )}
                                    </div>
                                </>
                            )}
                        </Step>

                        {data.apply && (
                            <>
                                {/* 2. The vehicle. */}
                                <Step
                                    id="vehicle"
                                    number={2}
                                    title={t(
                                        'applicants.intake.sections.vehicle.title',
                                    )}
                                    description={t(
                                        'applicants.intake.sections.vehicle.intro',
                                    )}
                                    state={states.vehicle}
                                >
                                    <div className="space-y-5">
                                        {existingMode && (
                                            <p className="rounded-md bg-muted px-4 py-3 text-sm text-muted-foreground">
                                                {t(
                                                    'applicants.intake.existing_vehicle_hint',
                                                )}
                                            </p>
                                        )}
                                        <div className="grid gap-5 @2xl:grid-cols-2">
                                            {text(
                                                'owner_name',
                                                t(
                                                    'applicants.intake.fields.owner',
                                                ),
                                                {
                                                    maxLength: 255,
                                                    autoComplete: 'off',
                                                    error: errors[
                                                        'vehicles.0.owner_name'
                                                    ],
                                                },
                                            )}
                                            <FormField
                                                id="vehicle_type_id"
                                                label={t(
                                                    'applicants.intake.fields.vehicle_type',
                                                )}
                                                error={
                                                    errors[
                                                        'vehicles.0.vehicle_type_id'
                                                    ]
                                                }
                                            >
                                                {(control) => (
                                                    <NativeSelect
                                                        {...control}
                                                        value={
                                                            data.vehicle_type_id
                                                        }
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'vehicle_type_id',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    >
                                                        <option value="">
                                                            {t(
                                                                'vehicles.choose_type',
                                                            )}
                                                        </option>
                                                        {vehicleTypes.map(
                                                            (type) => (
                                                                <option
                                                                    key={
                                                                        type.id
                                                                    }
                                                                    value={
                                                                        type.id
                                                                    }
                                                                >
                                                                    {type.name}
                                                                </option>
                                                            ),
                                                        )}
                                                    </NativeSelect>
                                                )}
                                            </FormField>
                                            {typeCode === OTHER_TYPE && (
                                                <div className="md:col-span-2">
                                                    {text(
                                                        'type_detail',
                                                        t(
                                                            'applicants.intake.type_detail',
                                                        ),
                                                        {
                                                            maxLength: 100,
                                                            autoComplete: 'off',
                                                            error: errors[
                                                                'vehicles.0.type_detail'
                                                            ],
                                                        },
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                        <PlateInput
                                            id="plate"
                                            label={t(
                                                'applicants.intake.fields.plate',
                                            )}
                                            value={data.plate}
                                            regions={plateRegions}
                                            error={errors['vehicles.0.plate']}
                                            onChange={(plate) =>
                                                form.setData('plate', plate)
                                            }
                                        />
                                        {canTow && (
                                            <PlateInput
                                                id="trailer_plate"
                                                label={t(
                                                    'applicants.intake.fields.trailer_plate',
                                                )}
                                                value={data.trailer_plate}
                                                regions={plateRegions}
                                                error={
                                                    errors['vehicles.1.plate']
                                                }
                                                onChange={(plate) =>
                                                    form.setData(
                                                        'trailer_plate',
                                                        plate,
                                                    )
                                                }
                                            />
                                        )}
                                        <InputError message={errors.vehicles} />
                                    </div>
                                </Step>

                                {/* 3. The permit. */}
                                <Step
                                    id="permit"
                                    number={3}
                                    title={t(
                                        'applicants.intake.sections.permit.title',
                                    )}
                                    description={t(
                                        'applicants.intake.sections.permit.intro',
                                        {
                                            max: String(
                                                limits.max_duration_days,
                                            ),
                                        },
                                    )}
                                    state={states.permit}
                                >
                                    <div className="space-y-5">
                                        <LetterFields
                                            value={{
                                                letter_number:
                                                    data.letter_number,
                                                letter_date: data.letter_date,
                                                letter_file: data.letter_file,
                                            }}
                                            errors={errors}
                                            onChange={(next) =>
                                                form.setData((current) => ({
                                                    ...current,
                                                    ...next,
                                                }))
                                            }
                                        />
                                        <fieldset
                                            aria-describedby={
                                                errors.permit_type
                                                    ? 'permit_type-error'
                                                    : undefined
                                            }
                                        >
                                            <legend className="mb-2 text-sm font-medium">
                                                {t(
                                                    'applicants.intake.fields.movement',
                                                )}
                                            </legend>
                                            <div className="grid gap-3 @md:grid-cols-2">
                                                {PERMIT_TYPES.map((type) => (
                                                    <label
                                                        key={type}
                                                        className="flex min-h-12 cursor-pointer items-center gap-3 rounded-card border-2 border-input px-4 py-2.5 font-semibold transition-colors hover:border-primary/50 has-checked:border-primary has-checked:bg-primary/5 has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="permit_type"
                                                            value={type}
                                                            checked={
                                                                data.permit_type ===
                                                                type
                                                            }
                                                            onChange={() =>
                                                                // A pass is for restricted hours: it always has a window.
                                                                form.setData(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        permit_type:
                                                                            type,
                                                                        any_time:
                                                                            type ===
                                                                            'pass'
                                                                                ? false
                                                                                : current.any_time,
                                                                    }),
                                                                )
                                                            }
                                                            className="size-5 accent-primary"
                                                        />
                                                        {t(
                                                            `permits.types.${type}`,
                                                        )}
                                                    </label>
                                                ))}
                                            </div>
                                            <InputError
                                                id="permit_type-error"
                                                role="alert"
                                                message={errors.permit_type}
                                                className="mt-2"
                                            />
                                        </fieldset>

                                        <FormField
                                            id="reason"
                                            label={t(
                                                'applicants.intake.fields.reason',
                                            )}
                                            error={errors.reason}
                                        >
                                            {(control) => (
                                                <Textarea
                                                    {...control}
                                                    rows={3}
                                                    maxLength={2000}
                                                    value={data.reason}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'reason',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormField>

                                        <div className="grid gap-5 @2xl:grid-cols-2">
                                            <EthiopianDateInput
                                                id="valid_from"
                                                label={t(
                                                    'applicants.intake.fields.valid_from',
                                                )}
                                                value={data.valid_from}
                                                error={errors.valid_from}
                                                onChange={(iso) =>
                                                    form.setData(
                                                        'valid_from',
                                                        iso,
                                                    )
                                                }
                                            />
                                            <EthiopianDateInput
                                                id="valid_to"
                                                label={t(
                                                    'applicants.intake.fields.valid_to',
                                                )}
                                                value={data.valid_to}
                                                error={errors.valid_to}
                                                onChange={(iso) =>
                                                    form.setData(
                                                        'valid_to',
                                                        iso,
                                                    )
                                                }
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                {t('permits.hours.title')}
                                            </p>
                                            <MovementHoursField
                                                idPrefix="walkin_hours"
                                                value={{
                                                    any_time: data.any_time,
                                                    move_from: data.move_from,
                                                    move_to: data.move_to,
                                                }}
                                                errors={errors}
                                                onChange={(next) =>
                                                    form.setData((current) => ({
                                                        ...current,
                                                        ...next,
                                                    }))
                                                }
                                            />
                                        </div>
                                        {estimate && (
                                            <div
                                                aria-live="polite"
                                                className="rounded-card border border-border bg-muted px-4 py-3"
                                            >
                                                <p className="text-eyebrow text-muted-foreground">
                                                    {t('applicants.intake.fee')}
                                                </p>
                                                {estimate.fee ? (
                                                    <p className="flex flex-wrap items-baseline gap-x-2">
                                                        <span className="text-xl font-extrabold tabular-nums">
                                                            {
                                                                estimate.fee
                                                                    .formatted
                                                            }
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            {t(
                                                                'payments.fee_days',
                                                                {
                                                                    days: estimate.days,
                                                                },
                                                            )}
                                                        </span>
                                                    </p>
                                                ) : (
                                                    <p className="font-medium">
                                                        {t(
                                                            'payments.fee_unknown',
                                                        )}
                                                    </p>
                                                )}
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {t(
                                                        'applicants.intake.fee_after_approval',
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </Step>

                                {/* 4. The confirmation. */}
                                <Step
                                    id="confirm"
                                    number={4}
                                    title={t(
                                        'applicants.intake.sections.confirm.title',
                                    )}
                                    description={t(
                                        'applicants.intake.sections.confirm.intro',
                                    )}
                                    state={states.confirm}
                                >
                                    <div className="space-y-5">
                                        <div>
                                            <label className="flex cursor-pointer items-start gap-3 rounded-card border-2 border-input p-4 font-semibold has-checked:border-primary has-checked:bg-primary/5 has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50">
                                                <input
                                                    id="confirmed"
                                                    type="checkbox"
                                                    checked={data.confirmed}
                                                    aria-invalid={
                                                        errors.confirmed
                                                            ? true
                                                            : undefined
                                                    }
                                                    aria-describedby={
                                                        errors.confirmed
                                                            ? 'confirmed-error'
                                                            : undefined
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'confirmed',
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                    className="mt-0.5 size-5 shrink-0 accent-primary"
                                                />
                                                {t('applicants.intake.confirm')}
                                            </label>
                                            <InputError
                                                id="confirmed-error"
                                                role="alert"
                                                message={errors.confirmed}
                                                className="mt-2"
                                            />
                                        </div>
                                    </div>
                                </Step>
                            </>
                        )}

                        <div className="@5xl/intake:hidden">
                            {buttons(false)}
                        </div>
                    </div>

                    <aside
                        aria-label={t('applicants.form.panel')}
                        className="order-first flex flex-col gap-4 @5xl/intake:sticky @5xl/intake:top-20 @5xl/intake:order-none"
                    >
                        <div className="overflow-hidden rounded-card border border-border bg-card">
                            <div className="brand-bar" aria-hidden="true" />
                            <div className="space-y-4 p-5">
                                <div>
                                    <p className="text-eyebrow text-muted-foreground">
                                        {t('applicants.form.progress')}
                                    </p>
                                    <p className="mt-1 text-sm font-semibold tabular-nums">
                                        {t('applicants.form.progress_count', {
                                            done: String(completed),
                                            total: String(required.length),
                                        })}
                                    </p>
                                    <div
                                        className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        <div
                                            className="h-full rounded-full bg-primary transition-[width]"
                                            style={{
                                                width: `${(completed / Math.max(required.length, 1)) * 100}%`,
                                            }}
                                        />
                                    </div>
                                </div>

                                <ol className="hidden space-y-1 @5xl/intake:block">
                                    {steps.map((step, position) => (
                                        <li key={step.key}>
                                            <a
                                                href={`#step-${step.key}`}
                                                className="flex items-center gap-3 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                            >
                                                <StepNumber
                                                    number={position + 1}
                                                    state={states[step.key]}
                                                />
                                                <span className="min-w-0 flex-1 truncate font-medium">
                                                    {t(step.title)}
                                                </span>
                                                {states[step.key] ===
                                                    'optional' && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {t(
                                                            'applicants.form.optional',
                                                        )}
                                                    </span>
                                                )}
                                            </a>
                                        </li>
                                    ))}
                                </ol>

                                {errorFields.length > 0 && (
                                    <p
                                        role="alert"
                                        className="flex items-start gap-2 rounded-md bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive"
                                    >
                                        <CircleAlert
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 shrink-0"
                                        />
                                        {t('applicants.form.errors', {
                                            count: String(errorFields.length),
                                        })}
                                    </p>
                                )}

                                {!existingMode && (
                                    <label className="flex cursor-pointer items-start gap-3 rounded-md border border-border p-3">
                                        <input
                                            type="checkbox"
                                            checked={data.apply}
                                            onChange={(event) =>
                                                form.setData(
                                                    'apply',
                                                    event.target.checked,
                                                )
                                            }
                                            aria-describedby="apply-hint"
                                            className="mt-0.5 size-4 accent-primary"
                                        />
                                        <span className="grid gap-0.5">
                                            <span className="text-sm font-semibold">
                                                {t('applicants.form.apply')}
                                            </span>
                                            <span
                                                id="apply-hint"
                                                className="text-xs text-muted-foreground"
                                            >
                                                {t(
                                                    'applicants.form.apply_hint',
                                                )}
                                            </span>
                                        </span>
                                    </label>
                                )}

                                <div className="hidden @5xl/intake:block">
                                    {buttons(true)}
                                </div>
                            </div>
                        </div>

                        <p className="hidden items-start gap-2 px-1 text-xs text-muted-foreground @5xl/intake:flex">
                            <MailCheck
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0"
                            />
                            {t('applicants.fields.contact_email_hint')}
                        </p>
                    </aside>
                </form>
            </div>
        </FormPageLayout>
    );
}
