import { Head, Link, useForm } from '@inertiajs/react';
import {
    Check,
    CircleAlert,
    MailCheck,
    Plus,
    Save,
    Send,
    Trash2,
    UserPlus,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PlateInput } from '@/components/plate-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { create, index, store } from '@/routes/admin/applicants';
import type { BreadcrumbItem, PlateRegionOption, VehicleType } from '@/types';
import { PERMIT_TYPES } from '@/types/enums';
import type { TranslationKey } from '@/types/translations';

type VehicleRow = {
    plate: string;
    vehicle_type_id: string;
    owner_name: string;
};

type Props = {
    /** Supported languages: code => name written in that language. */
    locales: Record<string, string>;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    limits: { max_trailers: number; max_duration_days: number };
};

type FormData = {
    contact_name: string;
    contact_email: string;
    locale: string;
    name_am: string;
    name_en: string;
    tin: string;
    phone: string;
    email: string;
    address: string;
    apply: boolean;
    submit: boolean;
    /** The vehicle first, then the trailers that were added. */
    vehicles: VehicleRow[];
    permit_type: string;
    valid_from: string;
    valid_to: string;
    reason: string;
};

type StepKey = 'contact' | 'organization' | 'vehicle' | 'trailers' | 'permit';

type StepState = 'done' | 'todo' | 'optional' | 'error';

const emptyVehicle = (): VehicleRow => ({
    plate: '',
    vehicle_type_id: '',
    owner_name: '',
});

const filled = (...values: string[]) =>
    values.every((value) => value.trim() !== '');

const vehicleFilled = (vehicle: VehicleRow) =>
    filled(vehicle.plate, vehicle.vehicle_type_id, vehicle.owner_name);

/** Which form fields belong to which step, to mark a step that has errors. */
const STEP_FIELDS: Record<StepKey, (field: string) => boolean> = {
    contact: (field) =>
        ['contact_name', 'contact_email', 'locale'].includes(field),
    organization: (field) =>
        ['name_am', 'name_en', 'tin', 'phone', 'email', 'address'].includes(
            field,
        ),
    vehicle: (field) => field === 'vehicles' || field.startsWith('vehicles.0.'),
    trailers: (field) => /^vehicles\.[1-9]/.test(field),
    permit: (field) =>
        ['permit_type', 'valid_from', 'valid_to', 'reason'].includes(field),
};

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
    action,
    children,
}: {
    id: StepKey;
    number: number;
    title: string;
    description: string;
    state: StepState;
    action?: ReactNode;
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
                {action}
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
 * The office's one-page form, as the paper form: the contact person (who
 * gets an e-mail to set a password), the organization, the vehicle and its
 * trailers, and the permit asked for — saved together, as a draft or
 * submitted for review. A sticky panel shows how far the form is and keeps
 * the buttons in reach. The permit part can be switched off to register
 * only the person.
 */
export default function ApplicantsCreate({
    locales,
    vehicleTypes,
    plateRegions,
    limits,
}: Props) {
    const { t, locale } = useTranslation();
    const form = useForm<FormData>({
        contact_name: '',
        contact_email: '',
        locale,
        name_am: '',
        name_en: '',
        tin: '',
        phone: '',
        email: '',
        address: '',
        apply: true,
        submit: true,
        vehicles: [emptyVehicle()],
        permit_type: '',
        valid_from: '',
        valid_to: '',
        reason: '',
    });
    // Errors for rows arrive under keys such as "vehicles.1.plate".
    const errors = form.errors as Partial<Record<string, string>>;
    const errorFields = Object.keys(errors);
    const { data } = form;
    const trailers = data.vehicles.slice(1);

    const save = (submitNow: boolean) => (event?: FormEvent) => {
        event?.preventDefault();
        form.transform((current) => ({ ...current, submit: submitNow }));
        form.post(store.url(), {
            preserveScroll: true,
            onError: (failed) => {
                // Bring the first wrong part into view.
                const first = (Object.keys(STEP_FIELDS) as StepKey[]).find(
                    (step) => Object.keys(failed).some(STEP_FIELDS[step]),
                );

                if (first) {
                    document.getElementById(`step-${first}`)?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    });
                }
            },
        });
    };

    const setVehicle = (row: number, field: keyof VehicleRow, value: string) =>
        form.setData(
            'vehicles',
            data.vehicles.map((vehicle, index) =>
                index === row ? { ...vehicle, [field]: value } : vehicle,
            ),
        );

    const addTrailer = () =>
        form.setData('vehicles', [...data.vehicles, emptyVehicle()]);

    const removeTrailer = (row: number) =>
        form.setData(
            'vehicles',
            data.vehicles.filter((_, index) => index !== row),
        );

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
        contact: stateOf(
            'contact',
            filled(data.contact_name, data.contact_email),
        ),
        organization: stateOf(
            'organization',
            filled(data.name_am, data.phone, data.email, data.address),
        ),
        vehicle: stateOf('vehicle', vehicleFilled(data.vehicles[0])),
        trailers: stateOf(
            'trailers',
            trailers.length > 0 && trailers.every(vehicleFilled),
            true,
        ),
        permit: stateOf(
            'permit',
            filled(
                data.permit_type,
                data.valid_from,
                data.valid_to,
                data.reason,
            ),
        ),
    };

    const steps: { key: StepKey; title: TranslationKey }[] = [
        { key: 'contact', title: 'applicants.contact_title' },
        { key: 'organization', title: 'applicants.organization' },
        ...(data.apply
            ? ([
                  { key: 'vehicle', title: 'applicants.form.vehicle_title' },
                  ...(limits.max_trailers > 0
                      ? [
                            {
                                key: 'trailers',
                                title: 'applicants.form.trailers_title',
                            },
                        ]
                      : []),
                  { key: 'permit', title: 'applicants.form.permit_title' },
              ] as { key: StepKey; title: TranslationKey }[])
            : []),
    ];
    const required = steps.filter((step) => step.key !== 'trailers');
    const completed = required.filter(
        (step) => states[step.key] === 'done',
    ).length;

    const text = (
        field: 'contact_name' | 'name_am' | 'name_en' | 'address',
        label: string,
        extra: {
            lang?: string;
            maxLength?: number;
            autoComplete?: string;
        } = {},
    ) => (
        <FormField id={field} label={label} error={errors[field]}>
            {(control) => (
                <Input
                    {...control}
                    {...extra}
                    value={data[field]}
                    onChange={(event) =>
                        form.setData(field, event.target.value)
                    }
                />
            )}
        </FormField>
    );

    const vehicleFields = (row: number) => {
        const vehicle = data.vehicles[row];
        const prefix = `vehicles.${row}`;

        return (
            <div className="space-y-5">
                <PlateInput
                    id={`vehicles_${row}_plate`}
                    value={vehicle.plate}
                    regions={plateRegions}
                    error={errors[`${prefix}.plate`]}
                    onChange={(plate) => setVehicle(row, 'plate', plate)}
                />
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id={`vehicles_${row}_vehicle_type_id`}
                        label={t('vehicles.fields.vehicle_type')}
                        error={errors[`${prefix}.vehicle_type_id`]}
                    >
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={vehicle.vehicle_type_id}
                                onChange={(event) =>
                                    setVehicle(
                                        row,
                                        'vehicle_type_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">
                                    {t('vehicles.choose_type')}
                                </option>
                                {vehicleTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField
                        id={`vehicles_${row}_owner_name`}
                        label={t('vehicles.fields.owner_name')}
                        error={errors[`${prefix}.owner_name`]}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                autoComplete="off"
                                maxLength={255}
                                value={vehicle.owner_name}
                                onChange={(event) =>
                                    setVehicle(
                                        row,
                                        'owner_name',
                                        event.target.value,
                                    )
                                }
                            />
                        )}
                    </FormField>
                </div>
            </div>
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
        { title: t('applicants.create_title'), href: create() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('applicants.create_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('applicants.create_title')}
                    description={t('applicants.create_intro')}
                />

                <form
                    onSubmit={save(data.apply)}
                    noValidate
                    className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] xl:grid-cols-[minmax(0,1fr)_21rem]"
                >
                    <div className="flex min-w-0 flex-col gap-6">
                        <Step
                            id="contact"
                            number={1}
                            title={t('applicants.contact_title')}
                            description={t('applicants.contact_intro')}
                            state={states.contact}
                        >
                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    {text(
                                        'contact_name',
                                        t('applicants.fields.contact_name'),
                                        {
                                            maxLength: 255,
                                            autoComplete: 'off',
                                        },
                                    )}
                                </div>
                                <FormField
                                    id="contact_email"
                                    label={t('applicants.fields.contact_email')}
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
                                <FormField
                                    id="locale"
                                    label={t('applicants.fields.locale')}
                                    error={errors.locale}
                                >
                                    {(control) => (
                                        <NativeSelect
                                            {...control}
                                            value={data.locale}
                                            onChange={(event) =>
                                                form.setData(
                                                    'locale',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {Object.entries(locales).map(
                                                ([code, name]) => (
                                                    <option
                                                        key={code}
                                                        value={code}
                                                    >
                                                        {name}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            </div>
                        </Step>

                        <Step
                            id="organization"
                            number={2}
                            title={t('applicants.organization')}
                            description={t('applicants.organization_intro')}
                            state={states.organization}
                        >
                            <div className="grid gap-5 md:grid-cols-2">
                                {text(
                                    'name_am',
                                    t('organization.fields.name_am'),
                                    {
                                        lang: 'am',
                                        maxLength: 255,
                                    },
                                )}
                                {text(
                                    'name_en',
                                    t('organization.fields.name_en'),
                                    {
                                        lang: 'en',
                                        maxLength: 255,
                                    },
                                )}
                                <FormField
                                    id="tin"
                                    label={t('organization.fields.tin')}
                                    hint={t('organization.fields.tin_hint')}
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
                                                        .replace(/\D/g, '')
                                                        .slice(0, 10),
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="phone"
                                    label={t('organization.fields.phone')}
                                    hint={t('organization.fields.phone_hint')}
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
                                <FormField
                                    id="email"
                                    label={t('organization.fields.email')}
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
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                                {text(
                                    'address',
                                    t('organization.fields.address'),
                                    {
                                        maxLength: 500,
                                    },
                                )}
                            </div>
                        </Step>

                        {data.apply && (
                            <>
                                <Step
                                    id="vehicle"
                                    number={3}
                                    title={t('applicants.form.vehicle_title')}
                                    description={t(
                                        'applicants.form.vehicle_intro',
                                    )}
                                    state={states.vehicle}
                                >
                                    {vehicleFields(0)}
                                    <InputError
                                        message={errors.vehicles}
                                        className="mt-3"
                                    />
                                </Step>

                                {limits.max_trailers > 0 && (
                                    <Step
                                        id="trailers"
                                        number={4}
                                        title={t(
                                            'applicants.form.trailers_title',
                                        )}
                                        description={t(
                                            'applicants.form.trailers_intro',
                                            {
                                                max: String(
                                                    limits.max_trailers,
                                                ),
                                            },
                                        )}
                                        state={states.trailers}
                                        action={
                                            trailers.length <
                                                limits.max_trailers && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={addTrailer}
                                                >
                                                    <Plus aria-hidden="true" />
                                                    {t(
                                                        'applicants.form.add_trailer',
                                                    )}
                                                </Button>
                                            )
                                        }
                                    >
                                        {trailers.length === 0 ? (
                                            <p className="rounded-card border border-dashed border-border-strong bg-muted px-4 py-5 text-center text-sm text-muted-foreground">
                                                {t(
                                                    'applicants.form.no_trailers',
                                                )}
                                            </p>
                                        ) : (
                                            <div className="space-y-4">
                                                {trailers.map((_, slot) => (
                                                    <div
                                                        key={slot}
                                                        role="group"
                                                        aria-labelledby={`trailer-${slot}-title`}
                                                        className="rounded-card border border-border p-4 sm:p-5"
                                                    >
                                                        <div className="mb-4 flex items-center justify-between gap-3">
                                                            <h3
                                                                id={`trailer-${slot}-title`}
                                                                className="text-sm font-semibold"
                                                            >
                                                                {t(
                                                                    'applicants.form.trailer',
                                                                    {
                                                                        number: String(
                                                                            slot +
                                                                                1,
                                                                        ),
                                                                    },
                                                                )}
                                                            </h3>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    removeTrailer(
                                                                        slot +
                                                                            1,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 aria-hidden="true" />
                                                                {t(
                                                                    'applicants.form.remove_trailer',
                                                                )}
                                                            </Button>
                                                        </div>
                                                        {vehicleFields(
                                                            slot + 1,
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </Step>
                                )}

                                <Step
                                    id="permit"
                                    number={limits.max_trailers > 0 ? 5 : 4}
                                    title={t('applicants.form.permit_title')}
                                    description={t(
                                        'applicants.form.permit_intro',
                                        {
                                            max: String(
                                                limits.max_duration_days,
                                            ),
                                        },
                                    )}
                                    state={states.permit}
                                >
                                    <div className="space-y-5">
                                        <fieldset
                                            aria-describedby={
                                                errors.permit_type
                                                    ? 'permit_type-error'
                                                    : undefined
                                            }
                                        >
                                            <legend className="mb-2 text-sm font-medium">
                                                {t(
                                                    'permits.fields.permit_type',
                                                )}
                                            </legend>
                                            <div className="grid gap-3 sm:grid-cols-2">
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
                                                                form.setData(
                                                                    'permit_type',
                                                                    type,
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

                                        <div className="grid gap-5 md:grid-cols-2">
                                            <EthiopianDateInput
                                                id="valid_from"
                                                label={t(
                                                    'permits.fields.valid_from',
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
                                                    'permits.fields.valid_to',
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

                                        <FormField
                                            id="reason"
                                            label={t('permits.fields.reason')}
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
                                    </div>
                                </Step>
                            </>
                        )}
                    </div>

                    <aside
                        aria-label={t('applicants.form.panel')}
                        className="flex flex-col gap-4 lg:sticky lg:top-20"
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

                                <ol className="space-y-1">
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
                                            {t('applicants.form.apply_hint')}
                                        </span>
                                    </span>
                                </label>

                                <div className="flex flex-col gap-2">
                                    {data.apply ? (
                                        <>
                                            <Button
                                                type="submit"
                                                size="lg"
                                                disabled={form.processing}
                                            >
                                                {form.processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Send aria-hidden="true" />
                                                )}
                                                {t('applicants.form.submit')}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="lg"
                                                variant="outline"
                                                disabled={form.processing}
                                                onClick={() => save(false)()}
                                            >
                                                <Save aria-hidden="true" />
                                                {t(
                                                    'applicants.form.save_draft',
                                                )}
                                            </Button>
                                        </>
                                    ) : (
                                        <Button
                                            type="submit"
                                            size="lg"
                                            disabled={form.processing}
                                        >
                                            {form.processing ? (
                                                <Spinner />
                                            ) : (
                                                <UserPlus aria-hidden="true" />
                                            )}
                                            {t('applicants.form.register_only')}
                                        </Button>
                                    )}
                                    <Button asChild variant="ghost">
                                        <Link href={index()}>
                                            {t('common.cancel')}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <p className="flex items-start gap-2 px-1 text-xs text-muted-foreground">
                            <MailCheck
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0"
                            />
                            {t('applicants.fields.contact_email_hint')}
                        </p>
                    </aside>
                </form>
            </div>
        </AppLayout>
    );
}
