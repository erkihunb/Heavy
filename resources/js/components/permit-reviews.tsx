import { useForm } from '@inertiajs/react';
import { CheckCircle2, MessageSquareText, Pencil, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { DualDate } from '@/components/dual-date';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PermitReview } from '@/types';
import { RECOMMENDATIONS } from '@/types/enums';
import type { Recommendation } from '@/types/enums';

/* What each recommendation looks like, in the form and in the list. */
const TONE: Record<
    Recommendation,
    { icon: typeof CheckCircle2; badge: string }
> = {
    approve: {
        icon: CheckCircle2,
        badge: 'bg-success-soft text-success-strong ring-success-strong/25',
    },
    changes: {
        icon: Pencil,
        badge: 'bg-warning-soft text-warning-strong ring-warning-strong/25',
    },
    reject: {
        icon: XCircle,
        badge: 'bg-danger-soft text-danger-strong ring-danger-strong/25',
    },
};

export function RecommendationBadge({
    recommendation,
}: {
    recommendation: Recommendation;
}) {
    const { t } = useTranslation();
    const Icon = TONE[recommendation].icon;

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1.5 rounded-badge px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                TONE[recommendation].badge,
            )}
        >
            <Icon aria-hidden="true" className="size-3.5" />
            {t(`permits.staff.recommended.${recommendation}`)}
        </span>
    );
}

/**
 * A Reviewer's assessment of an application under review: what they found,
 * in their own words, and the decision they recommend to the Approver. Both
 * are required. A Reviewer may save another review later; every review is
 * kept and the Approver reads them all. The server validates it again.
 */
export function ReviewForm({ action }: { action: string }) {
    const { t } = useTranslation();
    const form = useForm<{ recommendation: Recommendation | ''; note: string }>(
        { recommendation: '', note: '' },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(action, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            aria-labelledby="review-title"
            className="space-y-4 rounded-card border border-border bg-card p-5 shadow-sm"
        >
            <div className="space-y-1">
                <h3 id="review-title" className="text-section-title">
                    {t('permits.staff.recommend.title')}
                </h3>
                <p className="text-sm text-muted-foreground">
                    {t('permits.staff.recommend.hint')}
                </p>
            </div>

            <FormField
                id="review-note"
                required
                label={t('permits.staff.recommend.assessment')}
                error={form.errors.note}
            >
                {(control) => (
                    <Textarea
                        {...control}
                        value={form.data.note}
                        maxLength={2000}
                        rows={5}
                        required
                        placeholder={t(
                            'permits.staff.recommend.assessment_placeholder',
                        )}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                )}
            </FormField>

            <fieldset className="space-y-2">
                <legend className="mb-2 text-sm leading-none font-medium">
                    {t('permits.fields.recommendation')}
                    <span
                        aria-hidden="true"
                        className="ms-0.5 text-destructive"
                    >
                        *
                    </span>
                </legend>
                <div className="grid gap-2">
                    {RECOMMENDATIONS.map((choice) => {
                        const Icon = TONE[choice].icon;

                        return (
                            <label
                                key={choice}
                                className="flex cursor-pointer items-center gap-3 rounded-card border border-input px-4 py-3 text-sm font-medium has-checked:border-primary has-checked:bg-accent has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                            >
                                <input
                                    type="radio"
                                    name="review-recommendation"
                                    value={choice}
                                    checked={
                                        form.data.recommendation === choice
                                    }
                                    onChange={() =>
                                        form.setData('recommendation', choice)
                                    }
                                    className="size-4 accent-primary"
                                />
                                <Icon
                                    aria-hidden="true"
                                    className="size-4 text-muted-foreground"
                                />
                                {t(`permits.staff.recommend.${choice}`)}
                            </label>
                        );
                    })}
                </div>
                <InputError role="alert" message={form.errors.recommendation} />
            </fieldset>

            <Button
                type="submit"
                disabled={
                    form.processing ||
                    form.data.recommendation === '' ||
                    form.data.note.trim() === ''
                }
                className="w-full sm:w-auto"
            >
                {form.processing && <Spinner />}
                {t('permits.staff.recommend.submit')}
            </Button>
        </form>
    );
}

/**
 * Every Reviewer's assessment in this round, oldest first: what the Approver
 * reads before deciding. Internal: the applicant never sees it.
 */
export function ReviewList({ reviews }: { reviews: PermitReview[] }) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby="reviews-title"
            className="space-y-4 rounded-card border border-border bg-card p-5 shadow-sm"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <h3
                        id="reviews-title"
                        className="flex items-center gap-2 text-section-title"
                    >
                        <MessageSquareText
                            aria-hidden="true"
                            className="size-5 text-muted-foreground"
                        />
                        {t('permits.staff.reviews.title')}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {t('permits.staff.reviews.hint')}
                    </p>
                </div>
                {reviews.length > 0 && (
                    <span className="shrink-0 rounded-badge bg-muted px-2 py-0.5 text-xs font-medium tabular-nums">
                        {t('permits.staff.reviews.count', {
                            count: reviews.length,
                        })}
                    </span>
                )}
            </div>

            {reviews.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('permits.staff.reviews.empty')}
                </p>
            ) : (
                <ol className="space-y-3">
                    {reviews.map((review) => (
                        <li
                            key={review.id}
                            className="space-y-2 rounded-card border border-border bg-muted/40 p-4"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <RecommendationBadge
                                    recommendation={review.recommendation}
                                />
                                <span className="flex flex-wrap items-baseline gap-x-2 text-xs text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        {review.by}
                                    </span>
                                    {review.at && (
                                        <DualDate date={review.at} stacked />
                                    )}
                                    <span className="tabular-nums">
                                        {review.time}
                                    </span>
                                </span>
                            </div>
                            <p className="text-sm leading-relaxed whitespace-pre-line">
                                {review.assessment}
                            </p>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
