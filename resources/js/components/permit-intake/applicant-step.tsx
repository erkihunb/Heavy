import { ChevronDown } from 'lucide-react';
import { FormField } from '@/components/form-field';
import { OrganizationLookup } from '@/components/organization-lookup';
import type { OrganizationMatch } from '@/components/organization-lookup';
import { errorsOf } from '@/components/permit-intake/form';
import type { IntakeForm, StepState } from '@/components/permit-intake/form';
import { Step } from '@/components/permit-intake/step';
import { intakeTextField } from '@/components/permit-intake/text-field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * 1. The applicant: an organization on record, found with the lookup, or a
 * new one, registered with the request.
 */
export function ApplicantStep({
    form,
    state,
    locales,
    existingMode,
    existing,
    chooseMode,
    chooseExisting,
    showMore,
    setMoreOpen,
}: {
    form: IntakeForm;
    state: StepState;
    locales: Record<string, string>;
    existingMode: boolean;
    existing: OrganizationMatch | null;
    chooseMode: (useExisting: boolean) => void;
    chooseExisting: (match: OrganizationMatch | null) => void;
    showMore: boolean;
    setMoreOpen: (open: boolean) => void;
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
            description={t('applicants.intake.sections.applicant.intro')}
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
                            label={t('applicants.intake.fields.email')}
                            hint={t('applicants.fields.contact_email_hint')}
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
                            label={t('applicants.intake.fields.phone')}
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
                        {text(
                            'name_am',
                            t('applicants.intake.fields.organization'),
                            { lang: 'am', maxLength: 255 },
                        )}
                        <FormField
                            id="tin"
                            label={t('applicants.intake.fields.tin')}
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
                    </div>

                    <div className="mt-5 rounded-card border border-border">
                        <button
                            type="button"
                            aria-expanded={showMore}
                            aria-controls="organization-more"
                            onClick={() => setMoreOpen(!showMore)}
                            className="flex w-full items-center gap-3 rounded-card px-4 py-3 text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold">
                                    {t('applicants.intake.more')}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {t('applicants.intake.more_hint')}
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
                                    t('organization.fields.name_en'),
                                    {
                                        lang: 'en',
                                        maxLength: 255,
                                    },
                                )}
                                <FormField
                                    id="email"
                                    label={t('organization.fields.email')}
                                    hint={t('applicants.intake.org_email_hint')}
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
                                    { maxLength: 500 },
                                )}
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
                        )}
                    </div>
                </>
            )}
        </Step>
    );
}
