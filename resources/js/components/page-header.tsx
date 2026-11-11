import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { usePageBreadcrumbs } from '@/lib/page-context';

/**
 * The heading band at the top of an application screen (EUISIS layout): a
 * white band across the whole content area with the breadcrumbs above the
 * title, the description below it and the page's actions on the right.
 * It is drawn as the first element of the page container and reaches past
 * the container's padding to the edges of the content area.
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
    const breadcrumbs = usePageBreadcrumbs();
    const showBreadcrumbs = usePage().props.appearance?.showBreadcrumbs ?? true;

    return (
        <header className="relative isolate -mt-6 py-5 before:absolute before:inset-y-0 before:left-1/2 before:-z-10 before:w-screen before:-translate-x-1/2 before:border-b before:border-border before:bg-card">
            {showBreadcrumbs && breadcrumbs.length > 1 && (
                <div className="mb-2.5 text-sm">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            )}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-page-title break-words text-foreground">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1 max-w-3xl text-sm break-words text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action && (
                    <div className="flex w-full flex-wrap items-center gap-2 sm:ml-4 sm:w-auto sm:shrink-0 sm:flex-nowrap">
                        {action}
                    </div>
                )}
            </div>
        </header>
    );
}
