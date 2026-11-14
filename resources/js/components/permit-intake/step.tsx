import { Check, CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import type { StepKey, StepState } from '@/components/permit-intake/form';
import { cn } from '@/lib/utils';

/**
 * One numbered part of the form (EUISIS form section): the number — a tick
 * once it is complete, a warning when something is wrong — the title, a
 * line on what it is for, and the fields.
 */
export function Step({
    id,
    number,
    title,
    description,
    state,
    children,
}: {
    id: StepKey;
    number: number;
    title: string;
    description: string;
    state: StepState;
    children: ReactNode;
}) {
    return (
        <section
            id={`step-${id}`}
            aria-labelledby={`step-${id}-title`}
            className={cn(
                'scroll-mt-24 overflow-hidden rounded-card border bg-card',
                state === 'error' ? 'border-destructive/60' : 'border-border',
            )}
        >
            <header className="flex flex-wrap items-start gap-4 border-b border-border px-5 py-4 sm:px-6">
                <StepNumber number={number} state={state} />
                <div className="min-w-0 flex-1 basis-56">
                    <h2 id={`step-${id}-title`} className="text-section-title">
                        {title}
                    </h2>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
            </header>
            <div className="p-5 sm:p-6">{children}</div>
        </section>
    );
}

/** The step's number in a circle: a tick once complete, a warning on an error. */
export function StepNumber({
    number,
    state,
}: {
    number: number;
    state: StepState;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold tabular-nums',
                state === 'done' && 'bg-success text-success-foreground',
                state === 'error' && 'bg-destructive text-white',
                (state === 'todo' || state === 'optional') &&
                    'bg-primary/10 text-primary',
            )}
        >
            {state === 'done' ? (
                <Check className="size-4" strokeWidth={3} />
            ) : state === 'error' ? (
                <CircleAlert className="size-4" strokeWidth={2.5} />
            ) : (
                number
            )}
        </span>
    );
}
