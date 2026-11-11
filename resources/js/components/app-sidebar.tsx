import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    FileText,
    LayoutGrid,
    Truck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { show as organization } from '@/routes/organization';
import { index as permits } from '@/routes/permits';
import { index as reviewQueue } from '@/routes/staff/permits';
import { index as vehicles } from '@/routes/vehicles';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    // Which entries are shown is a convenience only; every route is authorised on the server.
    const isApplicant = auth.user.roles.includes('applicant');
    const isReviewingStaff = auth.user.roles.some((role) =>
        ['reviewer', 'approver', 'admin'].includes(role),
    );

    const mainNavItems: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(isApplicant
            ? [
                  {
                      title: t('nav.organization'),
                      href: organization(),
                      icon: Building2,
                  },
                  {
                      title: t('nav.vehicles'),
                      href: vehicles(),
                      icon: Truck,
                  },
                  {
                      title: t('nav.permits'),
                      href: permits(),
                      icon: FileText,
                  },
              ]
            : []),
        ...(isReviewingStaff
            ? [
                  {
                      title: t('nav.review_queue'),
                      href: reviewQueue(),
                      icon: ClipboardList,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
