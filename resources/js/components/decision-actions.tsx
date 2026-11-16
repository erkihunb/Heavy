import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';

type Decision = 'approve' | 'requestChanges' | 'reject';

/**
 * Approve, ask for changes, or reject: one note for the applicant and one
 * button per decision this person may take now. The note is optional when
 * approving and required otherwise; the server checks every decision again.
 */
export function DecisionActions({
    urls,
}: {
    /** URL each allowed decision is posted to; a decision left out is not offered. */
    urls: Partial<Record<Decision, string>>;
}) {
    const { t } = useTranslation();
    const form = useForm({ note: '' });
    const [sent, setSent] = useState<Decision | null>(null);

    const decisions: {
        key: Decision;
        label: string;
        variant: 'default' | 'outline' | 'destructive';
    }[] = [
        {
            key: 'approve',
            label: t('permits.staff.approve.submit'),
            variant: 'default',
        },
        {
            key: 'requestChanges',
            label: t('permits.staff.request_changes.submit'),
            variant: 'outline',
        },
        {
            key: 'reject',
            label: t('permits.staff.reject.submit'),
            variant: 'destructive',
        },
    ];
    const offered = decisions.filter((decision) => urls[decision.key]);

    if (offered.length === 0) {
        return null;
    }

    const decide = (decision: Decision): void => {
        const url = urls[decision];

        if (!url) {
            return;
        }

        setSent(decision);
        form.post(url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={(event) => event.preventDefault()}
            noValidate
            aria-labelledby="decide-title"
            className="space-y-4 rounded-card border border-border bg-card p-5"
        >
            <div className="space-y-1">
                <h3 id="decide-title" className="text-section-title">
                    {t('permits.staff.decide.title')}
                </h3>
                <p className="text-sm text-muted-foreground">
                    {t('permits.staff.decide.hint')}
                </p>
            </div>

            <FormField
                id="decide-note"
                label={t('permits.staff.note_for_applicant')}
                error={form.errors.note}
            >
                {(control) => (
                    <Textarea
                        {...control}
                        value={form.data.note}
                        maxLength={2000}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                )}
            </FormField>

            <div className="flex flex-wrap gap-2">
                {offered.map((decision) => (
                    <Button
                        key={decision.key}
                        type="button"
                        variant={decision.variant}
                        disabled={form.processing}
                        onClick={() => decide(decision.key)}
                    >
                        {form.processing && sent === decision.key && (
                            <Spinner />
                        )}
                        {decision.label}
                    </Button>
                ))}
            </div>
        </form>
    );
}
