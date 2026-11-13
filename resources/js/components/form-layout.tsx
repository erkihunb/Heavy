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

/** A titled card of related fields. */
export function FormSection({
    title,
    description,
    children,
    className,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'space-y-5 rounded-card border border-border bg-card p-5 sm:p-6',
                className,
            )}
        >
            {(title || description) && (
                <header className="space-y-1">
                    {title && (
                        <h2 className="text-sm font-semibold text-foreground">
                            {title}
                        </h2>
                    )}
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
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
