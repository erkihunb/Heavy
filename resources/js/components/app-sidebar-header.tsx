import { usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { DualDate } from '@/components/dual-date';
import { LanguageSwitcher } from '@/components/language-switcher';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { today } = usePage().props;

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-border bg-card px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 md:rounded-t-xl md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 size-10" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex shrink-0 items-center gap-4">
                <p className="hidden items-center gap-2 text-sm md:flex">
                    <CalendarDays
                        aria-hidden="true"
                        className="size-4 text-muted-foreground"
                    />
                    <DualDate date={today} />
                </p>
                <LanguageSwitcher />
            </div>
        </header>
    );
}
