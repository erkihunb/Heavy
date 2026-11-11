import { usePage } from '@inertiajs/react';
import { Banknote2Icon } from '@solar-icons/react/linear/banknote-2';
import { BillListIcon } from '@solar-icons/react/linear/bill-list';
import { Buildings2Icon } from '@solar-icons/react/linear/buildings-2';
import { ChartSquareIcon } from '@solar-icons/react/linear/chart-square';
import { ClipboardListIcon } from '@solar-icons/react/linear/clipboard-list';
import { DeliveryIcon } from '@solar-icons/react/linear/delivery';
import { DocumentTextIcon } from '@solar-icons/react/linear/document-text';
import { HistoryIcon } from '@solar-icons/react/linear/history';
import { QrCodeIcon } from '@solar-icons/react/linear/qr-code';
import { TagIcon } from '@solar-icons/react/linear/tag';
import { Tuning2Icon } from '@solar-icons/react/linear/tuning-2';
import { UserPlusRoundedIcon } from '@solar-icons/react/linear/user-plus-rounded';
import { UsersGroupRoundedIcon } from '@solar-icons/react/linear/users-group-rounded';
import { Widget2Icon } from '@solar-icons/react/linear/widget-2';
import { useTranslation } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { index as applicants } from '@/routes/admin/applicants';
import { index as auditLog } from '@/routes/admin/audit-log';
import { index as feeSchedules } from '@/routes/admin/fee-schedules';
import { overview as systemSettings } from '@/routes/admin/settings';
import { index as users } from '@/routes/admin/users';
import { index as vehicleTypes } from '@/routes/admin/vehicle-types';
import { verify as officerVerify } from '@/routes/officer';
import { show as organization } from '@/routes/organization';
import { index as permits } from '@/routes/permits';
import { index as paymentQueue } from '@/routes/staff/payments';
import { index as reviewQueue } from '@/routes/staff/permits';
import { index as reports } from '@/routes/staff/reports';
import { index as vehicles } from '@/routes/vehicles';
import type { NavItem } from '@/types';

export type NavGroup = {
    /** Section label; the first group has none. */
    label: string | null;
    items: NavItem[];
};

/**
 * The navigation of the signed-in area, grouped as in EUISIS and filtered by
 * role. Shared by the sidebar and the navigation search. Which entries are
 * shown is a convenience only: every route is authorised on the server.
 */
export function useNavGroups(): NavGroup[] {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const roles = auth.user.roles;

    const isApplicant = roles.includes('applicant');
    // Admins hold every staff permission (docs/DECISIONS.md).
    const isAdmin = roles.includes('admin');
    const has = (...names: string[]): boolean =>
        isAdmin || roles.some((role) => names.includes(role));

    const groups: NavGroup[] = [
        {
            label: null,
            items: [
                {
                    title: t('nav.dashboard'),
                    href: dashboard(),
                    icon: Widget2Icon,
                },
            ],
        },
        {
            label: t('nav.sections.applicant'),
            items: isApplicant
                ? [
                      {
                          title: t('nav.organization'),
                          href: organization(),
                          icon: Buildings2Icon,
                      },
                      {
                          title: t('nav.vehicles'),
                          href: vehicles(),
                          icon: DeliveryIcon,
                      },
                      {
                          title: t('nav.permits'),
                          href: permits(),
                          icon: DocumentTextIcon,
                      },
                  ]
                : [],
        },
        {
            label: t('nav.sections.back_office'),
            items: [
                ...(has('reviewer', 'approver')
                    ? [
                          {
                              title: t('nav.review_queue'),
                              href: reviewQueue(),
                              icon: ClipboardListIcon,
                          },
                      ]
                    : []),
                ...(has('cashier')
                    ? [
                          {
                              title: t('nav.payments'),
                              href: paymentQueue(),
                              icon: Banknote2Icon,
                          },
                      ]
                    : []),
                ...(has('approver', 'cashier')
                    ? [
                          {
                              title: t('nav.reports'),
                              href: reports(),
                              icon: ChartSquareIcon,
                          },
                      ]
                    : []),
                ...(has('registrar')
                    ? [
                          {
                              title: t('nav.applicants'),
                              href: applicants(),
                              icon: UserPlusRoundedIcon,
                          },
                      ]
                    : []),
                ...(has('traffic_officer')
                    ? [
                          {
                              title: t('verification.officer.nav'),
                              href: officerVerify(),
                              icon: QrCodeIcon,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: t('nav.sections.administration'),
            items: isAdmin
                ? [
                      {
                          title: t('nav.users'),
                          href: users(),
                          icon: UsersGroupRoundedIcon,
                      },
                      {
                          title: t('nav.fee_schedules'),
                          href: feeSchedules(),
                          icon: BillListIcon,
                      },
                      {
                          title: t('nav.vehicle_types'),
                          href: vehicleTypes(),
                          icon: TagIcon,
                      },
                      {
                          title: t('nav.system_settings'),
                          href: systemSettings(),
                          icon: Tuning2Icon,
                      },
                      {
                          title: t('nav.audit_log'),
                          href: auditLog(),
                          icon: HistoryIcon,
                      },
                  ]
                : [],
        },
    ];

    return groups.filter((group) => group.items.length > 0);
}
