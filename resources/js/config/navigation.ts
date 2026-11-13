import { dashboard } from '@/routes';
import { index as applicants } from '@/routes/admin/applicants';
import { index as auditLog } from '@/routes/admin/audit-log';
import { index as branches } from '@/routes/admin/branches';
import { index as feeSchedules } from '@/routes/admin/fee-schedules';
import { index as permissions } from '@/routes/admin/permissions';
import { index as permitRegister } from '@/routes/admin/permits';
import { index as roles } from '@/routes/admin/roles';
import { overview as systemSettings } from '@/routes/admin/settings';
import { index as users } from '@/routes/admin/users';
import { index as vehicleTypes } from '@/routes/admin/vehicle-types';
import { verify as officerVerify } from '@/routes/officer';
import { index as paymentQueue } from '@/routes/staff/payments';
import { index as reviewQueue } from '@/routes/staff/permits';
import { index as reports } from '@/routes/staff/reports';
import {
    BanknoteIcon,
    BillListIcon,
    Buildings3Icon,
    ChartIcon,
    ClipboardListIcon,
    DocumentsIcon,
    HistoryIcon,
    KeyIcon,
    LayoutDashboard,
    LockIcon,
    QrCodeIcon,
    ShieldUserIcon,
    TagsIcon,
    TuningIcon,
    UserPlusIcon,
    Users,
    WalletIcon,
} from '@/shell/Components/NavIcons';
import type { NavIcon } from '@/shell/Components/NavIcons';

/*
 * The signed-in menu of the admin shell: sections → groups → pages.
 *
 * - A section is a labelled block of the sidebar ("Back office").
 * - A group is a collapsible entry with an icon; on the collapsed rail it
 *   opens as a flyout. A group the person may see only one page of renders
 *   as that page's plain link.
 * - An item is one page. `permission` / `anyPermission` hide it from people
 *   who lack the permission (app/Enums/Permission.php). Hiding is a
 *   convenience only: every route is authorised on the server.
 *
 * The same list drives the sidebar, the breadcrumbs and the Ctrl+K command
 * palette. Labels are translation keys (lang/{en,am}/*.php).
 */

export type NavItem = {
    /** The page's address, from a Wayfinder route: `reviewQueue().url`. */
    href: string;
    labelKey: string;
    icon: NavIcon;
    /** Shown only to people holding this permission. */
    permission?: string;
    /** Shown to people holding any one of these. */
    anyPermission?: string[];
    /** Other path prefixes that belong under this item (sub-pages filed elsewhere). */
    owns?: string[];
};

export type NavGroup = {
    /** Stable id; remembers whether the group is open (localStorage). */
    key: string;
    labelKey: string;
    icon: NavIcon;
    items: NavItem[];
};

export type NavSection = {
    labelKey: string;
    groups: NavGroup[];
};

export type Navigation = {
    /** The first link: the dashboard, for those who hold its permission. */
    home: NavItem;
    sections: NavSection[];
};

export const navigation: Navigation = {
    home: {
        href: dashboard().url,
        labelKey: 'nav.dashboard',
        icon: LayoutDashboard,
        permission: 'dashboard.view',
    },
    sections: [
        {
            labelKey: 'nav.sections.back_office',
            groups: [
                {
                    key: 'processing',
                    labelKey: 'shell.menu.permit_processing',
                    icon: ClipboardListIcon,
                    items: [
                        {
                            href: reviewQueue().url,
                            labelKey: 'nav.review_queue',
                            icon: ClipboardListIcon,
                            permission: 'permits.view-queue',
                        },
                        // The review queue filtered to one stage of the workflow.
                        {
                            href: reviewQueue({
                                query: { status: 'awaiting_payment' },
                            }).url,
                            labelKey: 'dashboard.actions.collect',
                            icon: WalletIcon,
                            permission: 'payments.verify',
                        },
                        // Every permit, with a button per status ("Permits to issue" is the payment-confirmed one).
                        {
                            href: permitRegister().url,
                            labelKey: 'register.nav',
                            icon: DocumentsIcon,
                            anyPermission: [
                                'permits.view-queue',
                                'applicants.register',
                            ],
                        },
                    ],
                },
                {
                    key: 'payments',
                    labelKey: 'shell.menu.payments_reports',
                    icon: BanknoteIcon,
                    items: [
                        {
                            href: paymentQueue().url,
                            labelKey: 'nav.payments',
                            icon: BanknoteIcon,
                            permission: 'payments.verify',
                        },
                        {
                            href: reports().url,
                            labelKey: 'nav.reports',
                            icon: ChartIcon,
                            permission: 'reports.view',
                        },
                    ],
                },
                {
                    key: 'desk',
                    labelKey: 'shell.menu.front_desk',
                    icon: UserPlusIcon,
                    items: [
                        {
                            href: applicants().url,
                            labelKey: 'nav.applicants',
                            icon: UserPlusIcon,
                            permission: 'applicants.register',
                        },
                        {
                            href: officerVerify().url,
                            labelKey: 'verification.officer.nav',
                            icon: QrCodeIcon,
                            permission: 'verification.scan',
                        },
                    ],
                },
            ],
        },
        {
            labelKey: 'nav.sections.administration',
            groups: [
                {
                    key: 'access',
                    labelKey: 'shell.menu.access',
                    icon: ShieldUserIcon,
                    items: [
                        {
                            href: users().url,
                            labelKey: 'nav.users',
                            icon: Users,
                            permission: 'users.view',
                        },
                        {
                            href: roles().url,
                            labelKey: 'rbac.roles.title',
                            icon: KeyIcon,
                            permission: 'roles.view',
                        },
                        {
                            href: permissions().url,
                            labelKey: 'rbac.permissions.title',
                            icon: LockIcon,
                            permission: 'permissions.view',
                        },
                        {
                            href: auditLog().url,
                            labelKey: 'nav.audit_log',
                            icon: HistoryIcon,
                            permission: 'audit-log.view',
                        },
                    ],
                },
                {
                    key: 'configuration',
                    labelKey: 'shell.menu.configuration',
                    icon: TuningIcon,
                    items: [
                        {
                            href: feeSchedules().url,
                            labelKey: 'nav.fee_schedules',
                            icon: BillListIcon,
                            permission: 'fee-schedules.manage',
                        },
                        {
                            href: vehicleTypes().url,
                            labelKey: 'nav.vehicle_types',
                            icon: TagsIcon,
                            permission: 'vehicle-types.manage',
                        },
                        {
                            href: branches().url,
                            labelKey: 'nav.branches',
                            icon: Buildings3Icon,
                            permission: 'settings.manage',
                        },
                        {
                            href: systemSettings().url,
                            labelKey: 'nav.system_settings',
                            icon: TuningIcon,
                            permission: 'settings.manage',
                        },
                    ],
                },
            ],
        },
    ],
};
