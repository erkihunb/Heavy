import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { usePageBreadcrumbs } from '@/lib/page-context';

/**
 * The heading at the top of an application screen: the breadcrumbs above the
 * title, the description below it and the page's actions on the right. It
 * sits on the page's own background as the first element of the page
 * container, the same as the shell's heading (AuthenticatedLayout).
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
        <header>
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
