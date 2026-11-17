import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/lib/i18n';

type ActionData = {
    note: string;
};

/**
 * One decision a member of staff can take on an application: a short
 * explanation, an optional or required note, and the button that does it.
 * The server authorises and validates every submission again.
 */
export function StaffAction({
    id,
    title,
    hint,
    action,
    submitLabel,
    note = 'none',
    noteLabel,
    destructive = false,
}: {
    /** Unique on the page; prefixes the ids of the controls. */
    id: string;
    title: string;
    hint: string;
    /** URL the decision is posted to. */
    action: string;
    submitLabel: string;
    note?: 'none' | 'optional' | 'required';
    noteLabel?: string;
    destructive?: boolean;
}) {
    const { t } = useTranslation();
    const form = useForm<ActionData>({ note: '' });

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
            aria-labelledby={`${id}-title`}
            className="space-y-4 rounded-card border border-border bg-card p-5"
        >
            <div className="space-y-1">
                <h3 id={`${id}-title`} className="text-section-title">
                    {title}
                </h3>
                <p className="text-sm text-muted-foreground">{hint}</p>
            </div>

            {note !== 'none' && (
                <FormField
                    id={`${id}-note`}
                    label={
                        noteLabel ??
                        (note === 'optional'
                            ? t('permits.staff.note_optional')
                            : t('permits.fields.note'))
                    }
                    error={form.errors.note}
                >
                    {(control) => (
                        <Textarea
                            {...control}
                            value={form.data.note}
                            maxLength={2000}
                            required={note === 'required'}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                    )}
                </FormField>
            )}

            <Button
                type="submit"
                variant={destructive ? 'destructive' : 'default'}
                disabled={form.processing}
                className="w-full sm:w-auto"
            >
                {form.processing && <Spinner />}
                {submitLabel}
            </Button>
        </form>
    );
}
