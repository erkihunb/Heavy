import { FormField } from '@/components/form-field';
import { OrganizationLookup } from '@/components/organization-lookup';
import type { OrganizationMatch } from '@/components/organization-lookup';
import { errorsOf } from '@/components/permit-intake/form';
import type { IntakeForm, StepState } from '@/components/permit-intake/form';
import { Step } from '@/components/permit-intake/step';
import { intakeTextField } from '@/components/permit-intake/text-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * 1. The applicant: an organization on record, found with the lookup, or a
 * new one, registered with the request.
 */
export function ApplicantStep({
    form,
    state,
    existingMode,
    existing,
    chooseMode,
    chooseExisting,
    known = null,
}: {
    form: IntakeForm;
    state: StepState;
    existingMode: boolean;
    existing: OrganizationMatch | null;
    chooseMode: (useExisting: boolean) => void;
    chooseExisting: (match: OrganizationMatch | null) => void;
    /** An organization on record that the one being typed already is. */
    known?: OrganizationMatch | null;
}) {
    const { t } = useTranslation();
    const { data } = form;
    const errors = errorsOf(form);
    const text = intakeTextField(form);

    return (
        <Step
            id="applicant"
            number={1}
            title={t('applicants.intake.sections.applicant.title')}
            state={state}
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
                                checked={existingMode === option}
                                onChange={() => chooseMode(option)}
                                className="mt-0.5 size-4 accent-primary"
                            />
                            <span className="text-sm font-semibold">
                                {t(
                                    option
                                        ? 'applicants.intake.mode_existing'
                                        : 'applicants.intake.mode_new',
                                )}
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
                <div className="grid gap-5 @2xl:grid-cols-2">
                    {known && (
                        <div
                            role="status"
                            className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-primary/40 bg-primary/5 px-4 py-3 text-sm @2xl:col-span-2"
                        >
                            <span>
                                {t('applicants.intake.known', {
                                    organization: known.organization,
                                    tin: known.tin ?? '—',
                                })}
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => {
                                    chooseMode(true);
                                    chooseExisting(known);
                                }}
                            >
                                {t('applicants.intake.use_known')}
                            </Button>
                        </div>
                    )}
                    {text(
                        'name_am',
                        t('applicants.intake.fields.organization'),
                        { lang: 'am', maxLength: 255 },
                    )}
                    <FormField
                        id="tin"
                        label={t('applicants.intake.fields.tin')}
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
                        label={t('applicants.intake.fields.phone')}
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
                                    form.setData('phone', event.target.value)
                                }
                            />
                        )}
                    </FormField>
                    {text('contact_name', t('applicants.intake.fields.name'), {
                        maxLength: 255,
                        autoComplete: 'off',
                    })}
                    <FormField
                        id="contact_email"
                        label={t('applicants.intake.fields.email')}
                        error={errors.contact_email ?? errors.email}
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
                </div>
            )}
        </Step>
    );
}
