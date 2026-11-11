import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

type ControlProps = {
    id: string;
    'aria-describedby': string | undefined;
    'aria-invalid': true | undefined;
};

/**
 * A labelled form control with an optional hint and its validation error.
 * The control receives the ids it needs so assistive technology reads the
 * hint and the error together with the label.
 */
export function FormField({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: (control: ControlProps) => ReactNode;
}) {
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy =
        [hintId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
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
