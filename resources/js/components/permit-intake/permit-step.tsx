import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { LetterFields } from '@/components/letter-fields';
import { MovementHoursField } from '@/components/movement-hours-field';
import { errorsOf } from '@/components/permit-intake/form';
import type { IntakeForm, StepState } from '@/components/permit-intake/form';
import { Step } from '@/components/permit-intake/step';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import type { Money } from '@/types';
import { PERMIT_TYPES } from '@/types/enums';

/**
 * 3. The permit asked for: its type, dates, reason, movement hours and the
 * letter, with what it would cost by today's schedule.
 */
export function PermitStep({
    form,
    state,
    maxDurationDays,
    estimate,
}: {
    form: IntakeForm;
    state: StepState;
    maxDurationDays: number;
    estimate: { days: number; fee: Money | null } | null;
}) {
    const { t } = useTranslation();
    const { data } = form;
    const errors = errorsOf(form);

    return (
        <Step
            id="permit"
            number={3}
            title={t('applicants.intake.sections.permit.title')}
            description={t('applicants.intake.sections.permit.intro', {
                max: String(maxDurationDays),
            })}
            state={state}
        >
            <div className="space-y-5">
                <LetterFields
                    value={{
                        letter_number: data.letter_number,
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
                        errors.permit_type ? 'permit_type-error' : undefined
                    }
                >
                    <legend className="mb-2 text-sm font-medium">
                        {t('applicants.intake.fields.movement')}
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
                                    checked={data.permit_type === type}
                                    onChange={() =>
                                        // A pass is for restricted hours: it always has a window.
                                        form.setData((current) => ({
                                            ...current,
                                            permit_type: type,
                                            any_time:
                                                type === 'pass'
                                                    ? false
                                                    : current.any_time,
                                        }))
                                    }
                                    className="size-5 accent-primary"
                                />
                                {t(`permits.types.${type}`)}
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
                    label={t('applicants.intake.fields.reason')}
                    error={errors.reason}
                >
                    {(control) => (
                        <Textarea
                            {...control}
                            rows={3}
                            maxLength={2000}
                            value={data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    )}
                </FormField>

                <div className="grid gap-5 @2xl:grid-cols-2">
                    <EthiopianDateInput
                        id="valid_from"
                        label={t('applicants.intake.fields.valid_from')}
                        value={data.valid_from}
                        error={errors.valid_from}
                        onChange={(iso) => form.setData('valid_from', iso)}
                    />
                    <EthiopianDateInput
                        id="valid_to"
                        label={t('applicants.intake.fields.valid_to')}
                        value={data.valid_to}
                        error={errors.valid_to}
                        onChange={(iso) => form.setData('valid_to', iso)}
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
                                    {estimate.fee.formatted}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {t('payments.fee_days', {
                                        days: estimate.days,
                                    })}
                                </span>
                            </p>
                        ) : (
                            <p className="font-medium">
                                {t('payments.fee_unknown')}
                            </p>
                        )}
                        <p className="mt-1 text-xs text-muted-foreground">
                            {t('applicants.intake.fee_after_approval')}
                        </p>
                    </div>
                )}
            </div>
        </Step>
    );
}
