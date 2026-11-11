import type { ReactNode } from 'react';

/**
 * The title block at the top of an application screen.
 */
export function PageHeader({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1.5">
                <h1 className="text-3xl text-display sm:text-4xl">{title}</h1>
                {description && (
                    <p className="max-w-2xl text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </header>
    );
}
