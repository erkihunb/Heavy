import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/i18n';

type ControlProps = {
    id: string;
    'aria-describedby': string | undefined;
    'aria-invalid': true | undefined;
};

/**
 * A labelled form control with an optional hint and its validation error.
 * The control receives the ids it needs so assistive technology reads the
 * hint and the error together with the label. `required` marks the label
 * with an asterisk (and says "required" to screen readers): mark exactly the
 * fields the server requires.
 */
export function FormField({
    id,
    label,
    hint,
    error,
    required = false,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    required?: boolean;
    children: (control: ControlProps) => ReactNode;
}) {
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy =
        [hintId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>
                {label}
                {required && <RequiredMark />}
            </Label>
            {children({
                id,
                'aria-describedby': describedBy,
                'aria-invalid': error ? true : undefined,
            })}
            {hint && (
                <p id={hintId} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}

/** The asterisk after the label of a field the server requires, read out as "required". */
export function RequiredMark() {
    const { t } = useTranslation();

    return (
        <>
            <span className="ml-0.5 text-destructive" aria-hidden="true">
                *
            </span>
            <span className="sr-only"> ({t('common.required')})</span>
        </>
    );
}
