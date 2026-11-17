import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

/*
 * The building blocks every create and edit form shares, so they all read the
 * same way (as the shell's Users and Roles forms): the fields in titled
 * sections, and the buttons at the end, Cancel before the main action, on the
 * right — stacked full width on a phone, the main action on top.
 */

/**
 * A titled card of related fields. A form in steps numbers its sections;
 * `aside` holds what belongs beside the title (a count, a badge, a button).
 */
export function FormSection({
    title,
    description,
    step,
    required = false,
    aside,
    children,
    className,
}: {
    title?: string;
    description?: string;
    /** The section's number in a form filled in steps. */
    step?: number;
    /** Marks the section as one that must be filled in. */
    required?: boolean;
    aside?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'space-y-5 rounded-card border border-border bg-card p-5 shadow-sm sm:p-6',
                className,
            )}
        >
            {(title || description || aside) && (
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-3">
                        {step !== undefined && (
                            <span
                                aria-hidden="true"
                                className="grid size-8 shrink-0 place-items-center rounded-full bg-brand-soft text-sm font-semibold text-brand-strong tabular-nums"
                            >
                                {step}
                            </span>
                        )}
                        <div className="min-w-0 space-y-1">
                            {title && (
                                <h2 className="text-base leading-8 font-semibold text-foreground">
                                    {title}
                                    {required && (
                                        <span
                                            aria-hidden="true"
                                            className="ms-0.5 text-destructive"
                                        >
                                            *
                                        </span>
                                    )}
                                </h2>
                            )}
                            {description && (
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                    </div>
                    {aside && (
                        <div className="flex shrink-0 flex-wrap items-center gap-3">
                            {aside}
                        </div>
                    )}
                </header>
            )}
            {children}
        </section>
    );
}

/** The form's buttons: Cancel (a link back), then the main action that submits. */
export function FormActions({
    cancelHref,
    cancelLabel,
    submitLabel,
    processing,
    icon,
    children,
}: {
    cancelHref: string;
    cancelLabel: string;
    submitLabel: string;
    processing: boolean;
    /** Shown on the main button, replaced by a spinner while saving. */
    icon?: ReactNode;
    /** Further buttons, placed between Cancel and the main action. */
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            <Button asChild size="lg" variant="ghost">
                <Link href={cancelHref}>{cancelLabel}</Link>
            </Button>
            {children}
            <Button type="submit" size="lg" disabled={processing}>
                {processing ? <Spinner /> : icon}
                {submitLabel}
            </Button>
        </div>
    );
}
