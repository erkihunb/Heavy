import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { DualDate } from '@/components/dual-date';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';
import type { PermitComment } from '@/types';

/**
 * The head office's comments on an application: internal to the Authority,
 * oldest first, with a box to add one for those allowed to comment.
 */
export function PermitComments({
    comments,
    action,
}: {
    comments: PermitComment[];
    /** Where a new comment is posted; null when the viewer may not comment. */
    action: string | null;
}) {
    const { t } = useTranslation();
    const form = useForm({ body: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (action === null) {
            return;
        }

        form.post(action, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <section
            aria-labelledby="comments-title"
            className="space-y-4 rounded-card border border-border bg-card p-5 sm:p-6"
        >
            <div className="space-y-1">
                <h2 id="comments-title" className="text-section-title">
                    {t('permits.comments.title')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t('permits.comments.intro')}
                </p>
            </div>

            {comments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('permits.comments.empty')}
                </p>
            ) : (
                <ol className="space-y-3">
                    {comments.map((comment) => (
                        <li
                            key={comment.id}
                            className="rounded-card border border-border bg-muted/40 px-4 py-3"
                        >
                            <p className="flex flex-wrap items-baseline gap-x-2 text-xs text-muted-foreground">
                                <span className="font-semibold text-foreground">
                                    {comment.author}
                                </span>
                                <DualDate date={comment.at} />
                                <span className="tabular-nums">
                                    {comment.time}
                                </span>
                            </p>
                            <p className="mt-1.5 text-sm leading-relaxed whitespace-pre-line">
                                {comment.body}
                            </p>
                        </li>
                    ))}
                </ol>
            )}

            {action !== null && (
                <form onSubmit={submit} noValidate className="space-y-3">
                    <FormField
                        id="comment-body"
                        label={t('permits.comments.body')}
                        error={form.errors.body}
                    >
                        {(control) => (
                            <Textarea
                                {...control}
                                rows={3}
                                maxLength={2000}
                                placeholder={t('permits.comments.placeholder')}
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                            />
                        )}
                    </FormField>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {t('permits.comments.submit')}
                    </Button>
                </form>
            )}
        </section>
    );
}
