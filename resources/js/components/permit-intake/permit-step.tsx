import type { ComponentProps } from 'react';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FeeRateField, PermitEndDate } from '@/components/fee-rates';
import type { FeeRates } from '@/components/fee-rates';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { LetterFields } from '@/components/letter-fields';
import { MovementHoursField } from '@/components/movement-hours-field';
import { errorsOf } from '@/components/permit-intake/form';
import type { IntakeForm, StepState } from '@/components/permit-intake/form';
import { Step } from '@/components/permit-intake/step';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import { PERMIT_TYPES } from '@/types/enums';

/**
 * 3. The permit asked for: the letter, its type, reason and route, the fee
 * rate and the start date — the end date follows from the rate and is only
 * shown — and the movement hours.
 */
export function PermitStep({
    form,
    state,
    rates,
    letter,
    hasStoredScan = false,
}: {
    form: IntakeForm;
    state: StepState;
    rates: FeeRates;
    /** Correcting a request: a scan of the letter is already kept. */
    hasStoredScan?: boolean;
    /** The letter's fee, when it asks for several vehicles. */
    letter?: ComponentProps<typeof PermitEndDate>['letter'];
}) {
    const { t } = useTranslation();
    const { data } = form;
    const errors = errorsOf(form);

    return (
        <Step
            id="permit"
            number={3}
            title={t('applicants.intake.sections.permit.title')}
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
                    plain
                    hasStoredScan={hasStoredScan}
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

                <FeeRateField
                    rates={rates}
                    value={data.fee_schedule_id}
                    error={errors.fee_schedule_id}
                    onChange={(id) => form.setData('fee_schedule_id', id)}
                />

                <div className="grid items-start gap-5 @2xl:grid-cols-2">
                    <EthiopianDateInput
                        id="valid_from"
                        label={t('applicants.intake.fields.valid_from')}
                        value={data.valid_from}
                        error={errors.valid_from}
                        onChange={(iso) => form.setData('valid_from', iso)}
                    />
                    <PermitEndDate
                        label={t('applicants.intake.fields.valid_to')}
                        rates={rates}
                        value={data.fee_schedule_id}
                        validFrom={data.valid_from}
                        letter={letter}
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

                <FormField
                    id="route"
                    label={t('permits.fields.route')}
                    error={errors.route}
                >
                    {(control) => (
                        <Input
                            {...control}
                            maxLength={255}
                            placeholder={t('permits.fields.route_placeholder')}
                            value={data.route}
                            onChange={(event) =>
                                form.setData('route', event.target.value)
                            }
                        />
                    )}
                </FormField>

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
            </div>
        </Step>
    );
}
