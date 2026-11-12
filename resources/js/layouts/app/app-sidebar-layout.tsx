import { BreadcrumbsContext } from '@/lib/page-context';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import type { AppLayoutProps } from '@/types';

/**
 * The signed-in layout of pages not yet rewritten for the admin shell. They
 * get the shell's own sidebar, top bar, toasts and footer, so the menu looks
 * and behaves the same on every page; the page keeps its own heading band
 * (PageHeader), which reads the breadcrumbs from the context.
 */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AuthenticatedLayout bare>
            <BreadcrumbsContext.Provider value={breadcrumbs}>
                {children}
            </BreadcrumbsContext.Provider>
        </AuthenticatedLayout>
    );
}
