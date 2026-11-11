import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    Building2,
    ClipboardList,
    FileText,
    FileUp,
    LayoutGrid,
    Receipt,
    ScanLine,
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
import { index as feeSchedules } from '@/routes/admin/fee-schedules';
import { index as imports } from '@/routes/admin/imports';
import { verify as officerVerify } from '@/routes/officer';
import { show as organization } from '@/routes/organization';
import { index as permits } from '@/routes/permits';
import { index as paymentQueue } from '@/routes/staff/payments';
import { index as reviewQueue } from '@/routes/staff/permits';
import { index as vehicles } from '@/routes/vehicles';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    // Which entries are shown is a convenience only; every route is authorised on the server.
    const isApplicant = auth.user.roles.includes('applicant');
    // Admins hold every staff permission (docs/DECISIONS.md).
    const isAdmin = auth.user.roles.includes('admin');
    const isReviewingStaff =
        isAdmin ||
        auth.user.roles.some((role) => ['reviewer', 'approver'].includes(role));
    const isCashier = isAdmin || auth.user.roles.includes('cashier');
    const isOfficer = isAdmin || auth.user.roles.includes('traffic_officer');

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
        ...(isCashier
            ? [
                  {
                      title: t('nav.payments'),
                      href: paymentQueue(),
                      icon: Banknote,
                  },
              ]
            : []),
        ...(isOfficer
            ? [
                  {
                      title: t('verification.officer.nav'),
                      href: officerVerify(),
                      icon: ScanLine,
                  },
              ]
            : []),
        ...(isAdmin
            ? [
                  {
                      title: t('nav.fee_schedules'),
                      href: feeSchedules(),
                      icon: Receipt,
                  },
                  {
                      title: t('nav.imports'),
                      href: imports(),
                      icon: FileUp,
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
