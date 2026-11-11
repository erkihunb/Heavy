import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashMessage } from '@/components/flash-message';
import { useTranslation } from '@/lib/i18n';
import { BreadcrumbsContext } from '@/lib/page-context';
import type { AppLayoutProps } from '@/types';

/**
 * The signed-in shell (EUISIS layout): the sidebar, the top bar, the page —
 * whose heading band (PageHeader) shows the breadcrumbs above its title —
 * and a quiet footer.
 */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { t } = useTranslation();

    return (
        <AppShell variant="sidebar">
            <a
                href="#main-content"
                className="sr-only rounded-md bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-60"
            >
                {t('common.skip_to_content')}
            </a>
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader />
                <div
                    id="main-content"
                    tabIndex={-1}
                    className="flex min-w-0 flex-1 flex-col outline-none"
                >
                    <BreadcrumbsContext.Provider value={breadcrumbs}>
                        <FlashMessage />
                        {children}
                    </BreadcrumbsContext.Provider>
                </div>
                <footer className="shrink-0 border-t border-border px-4 py-3 text-xs text-muted-foreground sm:px-6 lg:px-8">
                    <p className="mx-auto w-full max-w-[1600px]">
                        {t('common.authority')}
                    </p>
                </footer>
            </AppContent>
        </AppShell>
    );
}
