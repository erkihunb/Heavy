import type { ComponentType, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * A section of a page (EUISIS SectionCard): a header band with a tinted
 * icon, the title and a one-line description, then the content.
 */
export function SectionCard({
    id,
    icon: Icon,
    title,
    description,
    actions,
    flush = false,
    className,
    children,
}: {
    id: string;
    icon?: ComponentType<{ className?: string }>;
    title: string;
    description?: string;
    actions?: ReactNode;
    /** The content brings its own padding (a table, a list of rows). */
    flush?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={id}
            className={cn(
                'min-w-0 overflow-hidden rounded-card border border-border bg-card',
                className,
            )}
        >
            <div className="flex flex-wrap items-start gap-3 border-b border-border px-5 py-4">
                {Icon && (
                    <span
                        aria-hidden="true"
                        className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary"
                    >
                        <Icon className="size-[18px]" />
                    </span>
                )}
                <div className="min-w-0 flex-1 basis-56">
                    <h2 id={id} className="text-section-title text-foreground">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="flex shrink-0 flex-wrap gap-2">
                        {actions}
                    </div>
                )}
            </div>
            <div className={cn(!flush && 'p-5')}>{children}</div>
        </section>
    );
}
