import { CircleAlert } from 'lucide-react';
import { IntakeButtons } from '@/components/permit-intake/buttons';
import type { SaveIntake } from '@/components/permit-intake/buttons';
import { errorsOf } from '@/components/permit-intake/form';
import type {
    IntakeForm,
    StepKey,
    StepState,
} from '@/components/permit-intake/form';
import { formatBirr } from '@/components/permit-intake/smart';
import type { FeeEstimate } from '@/components/permit-intake/smart';
import { StepNumber } from '@/components/permit-intake/step';
import { useTranslation } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translations';

/**
 * The sticky panel beside the form: how far it is, each step's state, the
 * number of errors, the switch for registering without a request, and the
 * buttons.
 */
export function ProgressPanel({
    form,
    steps,
    states,
    existingMode,
    save,
    fee,
    typeName,
}: {
    form: IntakeForm;
    steps: { key: StepKey; title: TranslationKey }[];
    states: Record<StepKey, StepState>;
    existingMode: boolean;
    save: SaveIntake;
    /** The letter's fee, added up while the form is filled in. */
    fee?: FeeEstimate;
    typeName?: (id: string) => string;
}) {
    const { t } = useTranslation();
    const { data } = form;
    const errorFields = Object.keys(errorsOf(form));
    const required = steps;
    const completed = required.filter(
        (step) => states[step.key] === 'done',
    ).length;

    return (
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

                    {fee && data.apply && fee.vehicles > 0 && (
                        <div
                            className="rounded-md border border-border bg-muted/40 px-3 py-2"
                            aria-live="polite"
                        >
                            <p className="text-eyebrow text-muted-foreground">
                                {t('applicants.intake.fee_total')}
                            </p>
                            {fee.missing.length > 0 ? (
                                <p className="mt-1 text-sm text-destructive">
                                    {t('applicants.intake.fee_missing', {
                                        types: fee.missing
                                            .map((id) => typeName?.(id) ?? id)
                                            .join(', '),
                                    })}
                                </p>
                            ) : (
                                <p className="mt-1 text-sm font-semibold tabular-nums">
                                    {fee.totalCents === null
                                        ? '—'
                                        : formatBirr(fee.totalCents)}
                                    <span className="ml-1 font-normal text-muted-foreground">
                                        {t('applicants.intake.fee_vehicles', {
                                            count: fee.vehicles,
                                        })}
                                    </span>
                                </p>
                            )}
                        </div>
                    )}

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
                                    {states[step.key] === 'optional' && (
                                        <span className="text-xs text-muted-foreground">
                                            {t('applicants.form.optional')}
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
                                    form.setData('apply', event.target.checked)
                                }
                                className="mt-0.5 size-4 accent-primary"
                            />
                            <span className="text-sm font-semibold">
                                {t('applicants.form.apply')}
                            </span>
                        </label>
                    )}

                    <div className="hidden @5xl/intake:block">
                        <IntakeButtons form={form} stacked save={save} />
                    </div>
                </div>
            </div>
        </aside>
    );
}
