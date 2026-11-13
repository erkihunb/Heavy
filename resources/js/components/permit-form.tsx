import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FeeEstimate } from '@/components/fee-estimate';
import { FormField, RequiredMark } from '@/components/form-field';
import { FormActions } from '@/components/form-layout';
import InputError from '@/components/input-error';
import { LetterFields } from '@/components/letter-fields';
import { MovementHoursField } from '@/components/movement-hours-field';
import type { MovementHoursValue } from '@/components/movement-hours-field';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import type { Vehicle } from '@/types';
import { PERMIT_TYPES } from '@/types/enums';
import type { PermitType } from '@/types/enums';

export type PermitFormData = {
    permit_type: PermitType | '';
    primary_vehicle_id: string;
    trailer_vehicle_ids: string[];
    /** Gregorian ISO dates; the date picker converts from the Ethiopian calendar. */
    valid_from: string;
    valid_to: string;
    reason: string;
    /** The branch applied to; empty for the head office. Only applicants choose it. */
    branch_id?: string;
    /** The request letter: only when a Registrar records a request taken at the office. */
    letter_number?: string;
    letter_date?: string;
    letter_file?: File | null;
} & MovementHoursValue;

export type PermitLimits = {
    max_trailers: number;
    max_duration_days: number;
};

function Section({
    id,
    step,
    title,
    hint,
    children,
}: {
    id: string;
    step: number;
    title: string;
    hint: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={`${id}-title`}
            className="space-y-5 rounded-card border border-border bg-card p-5 sm:p-6"
        >
            <div className="flex items-start gap-4">
                <span
                    aria-hidden="true"
                    className="flex size-9 shrink-0 items-center justify-center rounded-card bg-highlight font-extrabold text-highlight-foreground tabular-nums"
                >
                    {step}
                </span>
                <div className="space-y-1">
                    <h2 id={`${id}-title`} className="text-section-title">
                        {title}
                    </h2>
                    <p className="text-sm text-muted-foreground">{hint}</p>
                </div>
            </div>
            {children}
        </section>
    );
}

/**
 * The fields shared by starting and editing an application, in the order of
 * the old paper form: permit type, vehicles, dates, reason. Saving keeps a
 * draft; submitting happens on the next screen.
 */
export function PermitForm({
    form,
    onSubmit,
    vehicles,
    limits,
    cancelHref,
    estimateUrl,
    branches = [],
    letter,
}: {
    form: InertiaFormProps<PermitFormData>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    vehicles: Vehicle[];
    limits: PermitLimits;
    cancelHref: string;
    /** The fee estimate address for the applicant the form is filled for. */
    estimateUrl: string;
    /** The branches an applicant may apply to; none hides the choice. */
    branches?: { id: number; code: string; name: string }[];
    /** Ask for the request letter (a Registrar at the office); `hasStoredScan` on an edit. */
    letter?: { hasStoredScan: boolean };
}) {
    const { t } = useTranslation();
    // Errors for array items arrive under keys such as "trailer_vehicle_ids.0".
    const errors = form.errors as Partial<Record<string, string>>;

    const vehicleLabel = (vehicle: Vehicle) =>
        `${vehicle.plate} · ${vehicle.vehicle_type.name}`;

    const trailerSlots = Array.from(
        { length: limits.max_trailers },
        (_, slot) => form.data.trailer_vehicle_ids[slot] ?? '',
    );

    const setTrailer = (slot: number, vehicleId: string) => {
        const next = [...trailerSlots];
        next[slot] = vehicleId;
        form.setData(
            'trailer_vehicle_ids',
            next.filter((id) => id !== ''),
        );
    };

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-5">
            {letter && (
                <LetterFields
                    value={{
                        letter_number: form.data.letter_number ?? '',
                        letter_date: form.data.letter_date ?? '',
                        letter_file: form.data.letter_file ?? null,
                    }}
                    errors={errors}
                    hasStoredScan={letter.hasStoredScan}
                    onChange={(next) =>
                        form.setData((current) => ({ ...current, ...next }))
                    }
                />
            )}
            <Section
                id="section-type"
                step={1}
                title={t('permits.form.sections.type.title')}
                hint={t('permits.form.sections.type.hint')}
            >
                <fieldset
                    aria-describedby={
                        errors.permit_type ? 'permit_type-error' : undefined
                    }
                    className="grid gap-3 sm:grid-cols-2"
                >
                    <legend className="sr-only">
                        {t('permits.fields.permit_type')}
                        <RequiredMark />
                    </legend>
                    {PERMIT_TYPES.map((type) => (
                        <label
                            key={type}
                            className="flex min-h-14 cursor-pointer items-center gap-3 rounded-card border-2 border-input px-4 py-3 text-lg font-bold has-checked:border-primary has-checked:bg-accent has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                        >
                            <input
                                type="radio"
                                name="permit_type"
                                value={type}
                                checked={form.data.permit_type === type}
                                onChange={() =>
                                    // A pass is for restricted hours: it always has a window.
                                    form.setData((data) => ({
                                        ...data,
                                        permit_type: type,
                                        any_time:
                                            type === 'pass'
                                                ? false
                                                : data.any_time,
                                    }))
                                }
                                className="size-5 accent-primary"
                            />
                            {t(`permits.types.${type}`)}
                        </label>
                    ))}
                </fieldset>
                <InputError
                    id="permit_type-error"
                    role="alert"
                    message={errors.permit_type}
                />
            </Section>

            <Section
                id="section-vehicles"
                step={2}
                title={t('permits.form.sections.vehicles.title')}
                hint={t('permits.form.sections.vehicles.hint', {
                    max: limits.max_trailers,
                })}
            >
                <FormField
                    id="primary_vehicle_id"
                    required
                    label={t('permits.fields.primary_vehicle')}
                    error={errors.primary_vehicle_id}
                >
                    {(control) => (
                        <NativeSelect
                            {...control}
                            value={form.data.primary_vehicle_id}
                            onChange={(event) =>
                                form.setData(
                                    'primary_vehicle_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">
                                {t('permits.form.choose_vehicle')}
                            </option>
                            {vehicles.map((vehicle) => (
                                <option key={vehicle.id} value={vehicle.id}>
                                    {vehicleLabel(vehicle)}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                </FormField>

                {trailerSlots.map((selected, slot) => (
                    <FormField
                        key={slot}
                        id={`trailer_vehicle_ids_${slot}`}
                        label={t('permits.form.trailer_number', {
                            number: slot + 1,
                        })}
                        error={
                            errors[`trailer_vehicle_ids.${slot}`] ??
                            (slot === 0
                                ? errors.trailer_vehicle_ids
                                : undefined)
                        }
                    >
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={selected}
                                onChange={(event) =>
                                    setTrailer(slot, event.target.value)
                                }
                            >
                                <option value="">
                                    {t('permits.form.no_trailer')}
                                </option>
                                {vehicles
                                    .filter(
                                        (vehicle) =>
                                            vehicle.id === selected ||
                                            (vehicle.id !==
                                                form.data.primary_vehicle_id &&
                                                !trailerSlots.includes(
                                                    vehicle.id,
                                                )),
                                    )
                                    .map((vehicle) => (
                                        <option
                                            key={vehicle.id}
                                            value={vehicle.id}
                                        >
                                            {vehicleLabel(vehicle)}
                                        </option>
                                    ))}
                            </NativeSelect>
                        )}
                    </FormField>
                ))}
            </Section>

            <Section
                id="section-dates"
                step={3}
                title={t('permits.form.sections.dates.title')}
                hint={t('permits.form.sections.dates.hint', {
                    max: limits.max_duration_days,
                })}
            >
                <div className="grid gap-6 md:grid-cols-2">
                    <EthiopianDateInput
                        id="valid_from"
                        required
                        label={t('permits.fields.valid_from')}
                        value={form.data.valid_from}
                        error={errors.valid_from}
                        onChange={(iso) => form.setData('valid_from', iso)}
                    />
                    <EthiopianDateInput
                        id="valid_to"
                        required
                        label={t('permits.fields.valid_to')}
                        value={form.data.valid_to}
                        error={errors.valid_to}
                        onChange={(iso) => form.setData('valid_to', iso)}
                    />
                </div>
                <FeeEstimate
                    permitType={form.data.permit_type}
                    primaryVehicleId={form.data.primary_vehicle_id}
                    validFrom={form.data.valid_from}
                    validTo={form.data.valid_to}
                    url={estimateUrl}
                />
            </Section>

            <Section
                id="section-hours"
                step={4}
                title={t('permits.hours.title')}
                hint={t('permits.hours.intro')}
            >
                <MovementHoursField
                    value={{
                        any_time: form.data.any_time,
                        move_from: form.data.move_from,
                        move_to: form.data.move_to,
                    }}
                    errors={errors}
                    onChange={(next) =>
                        form.setData((data) => ({ ...data, ...next }))
                    }
                />
            </Section>

            <Section
                id="section-reason"
                step={5}
                title={t('permits.form.sections.reason.title')}
                hint={t('permits.form.sections.reason.hint')}
            >
                <FormField
                    id="reason"
                    required
                    label={t('permits.fields.reason')}
                    error={errors.reason}
                >
                    {(control) => (
                        <Textarea
                            {...control}
                            value={form.data.reason}
                            maxLength={2000}
                            required
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    )}
                </FormField>

                {branches.length > 0 && (
                    <FormField
                        id="branch_id"
                        label={t('permits.fields.branch')}
                        hint={t('permits.fields.branch_hint')}
                        error={errors.branch_id}
                    >
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={form.data.branch_id ?? ''}
                                onChange={(event) =>
                                    form.setData(
                                        'branch_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">
                                    {t('permits.fields.head_office')}
                                </option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                )}
            </Section>

            <div className="space-y-4">
                <p className="text-sm text-muted-foreground sm:text-right">
                    {t('permits.form.next_step')}
                </p>
                <FormActions
                    cancelHref={cancelHref}
                    cancelLabel={t('permits.form.cancel')}
                    submitLabel={t('permits.form.save')}
                    processing={form.processing}
                />
            </div>
        </form>
    );
}
