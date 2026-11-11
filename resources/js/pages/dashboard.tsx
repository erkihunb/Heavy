import { Head, Link, usePage } from '@inertiajs/react';
import { Banknote2Icon } from '@solar-icons/react/linear/banknote-2';
import { CalendarMarkIcon } from '@solar-icons/react/linear/calendar-mark';
import { ChartSquareIcon } from '@solar-icons/react/linear/chart-square';
import { ClipboardListIcon } from '@solar-icons/react/linear/clipboard-list';
import { DeliveryIcon } from '@solar-icons/react/linear/delivery';
import { DocumentTextIcon } from '@solar-icons/react/linear/document-text';
import { HourglassIcon } from '@solar-icons/react/linear/hourglass';
import { QrCodeIcon } from '@solar-icons/react/linear/qr-code';
import { ShieldCheckIcon } from '@solar-icons/react/linear/shield-check';
import { WalletMoneyIcon } from '@solar-icons/react/linear/wallet-money';
import { ArrowRight } from 'lucide-react';
import type { ComponentType } from 'react';
import {
    EmptyRow,
    Table,
    TableCard,
    TBody,
    Td,
    Th,
    THead,
    Tr,
} from '@/components/data-table';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { verify as officerVerify } from '@/routes/officer';
import { show as organization } from '@/routes/organization';
import {
    create as applyForPermit,
    index as permitList,
    show as permitPage,
} from '@/routes/permits';
import { index as paymentQueue } from '@/routes/staff/payments';
import {
    index as reviewQueue,
    show as staffPermitPage,
} from '@/routes/staff/permits';
import { index as reports } from '@/routes/staff/reports';
import { show as twoFactor } from '@/routes/two-factor';
import {
    create as registerVehicle,
    index as vehicleList,
} from '@/routes/vehicles';
import type { BreadcrumbItem, DualDate as DualDateValue, Money } from '@/types';
import { PERMIT_STATUSES } from '@/types/enums';
import type { PermitStatus, PermitType } from '@/types/enums';
import type { TranslationKey } from '@/types/translations';

type CountKey =
    | 'vehicles'
    | 'in_force'
    | 'in_progress'
    | 'changes_requested'
    | 'awaiting_payment'
    | 'waiting_review'
    | 'payments_to_verify'
    | 'expiring'
    | 'scans_today';

type Props = {
    account: { emailVerified: boolean; twoFactorEnabled: boolean };
    registration: { hasOrganization: boolean; vehicleCount: number } | null;
    counts: Partial<Record<CountKey, number>>;
    month: { label: string; issued: number; revenue: Money } | null;
    distribution: Record<PermitStatus, number> | null;
    activity:
        | {
              id: number;
              permit_id: string;
              reference: string;
              organization: string | null;
              from: PermitStatus | null;
              to: PermitStatus;
              at: DualDateValue;
              time: string;
              actor: string | null;
              staff_link: boolean;
          }[]
        | null;
    recentPermits:
        | {
              id: string;
              permit_number: string | null;
              permit_type: PermitType;
              status: PermitStatus;
              valid_from: DualDateValue;
              valid_to: DualDateValue;
          }[]
        | null;
};

type Tone = 'primary' | 'success' | 'warning' | 'critical' | 'neutral';

/* EUISIS KPI tones: a solid icon tile in light, a quiet one in dark. */
const TONE_ICON: Record<Tone, string> = {
    primary:
        'bg-primary text-primary-foreground dark:bg-primary/15 dark:text-primary',
    success:
        'bg-emerald-700 text-white dark:bg-emerald-950/60 dark:text-emerald-400',
    warning: 'bg-amber-700 text-white dark:bg-amber-950/60 dark:text-amber-400',
    critical: 'bg-red-700 text-white dark:bg-red-950/60 dark:text-red-400',
    neutral:
        'bg-muted-foreground text-white dark:bg-muted dark:text-muted-foreground',
};

const TONE_EDGE: Record<Tone, string> = {
    primary: 'before:bg-primary',
    success: 'before:bg-emerald-500',
    warning: 'before:bg-amber-500',
    critical: 'before:bg-red-500',
    neutral: 'before:bg-border-strong',
};

/* Colour of each status in the distribution bar. */
const STATUS_COLOR: Record<PermitStatus, string> = {
    draft: 'bg-zinc-300 dark:bg-zinc-600',
    submitted: 'bg-primary/60',
    under_review: 'bg-primary',
    changes_requested: 'bg-amber-400',
    rejected: 'bg-red-500',
    awaiting_payment: 'bg-amber-600',
    payment_submitted: 'bg-sky-500',
    issued: 'bg-emerald-400',
    active: 'bg-emerald-600',
    expired: 'bg-zinc-400 dark:bg-zinc-500',
    revoked: 'bg-red-700',
    withdrawn: 'bg-zinc-200 dark:bg-zinc-700',
};

type Kpi = {
    key: string;
    label: string;
    value: string | number;
    icon: ComponentType<{ className?: string }>;
    tone: Tone;
    href?: string;
};

function KpiCard({ kpi }: { kpi: Kpi }) {
    const body = (
        <>
            <div className="flex items-start justify-between gap-4">
                <p className="min-w-0 text-xs leading-relaxed font-medium text-muted-foreground sm:text-sm">
                    {kpi.label}
                </p>
                <span
                    aria-hidden="true"
                    className={cn(
                        'hidden size-7 shrink-0 place-items-center rounded-md sm:grid',
                        TONE_ICON[kpi.tone],
                    )}
                >
                    <kpi.icon className="size-4" />
                </span>
            </div>
            <p className="pt-3 text-2xl leading-tight font-semibold tracking-tight tabular-nums sm:text-3xl">
                {kpi.value}
            </p>
        </>
    );

    const className = cn(
        'relative flex h-full flex-col overflow-hidden rounded-card border border-border bg-card p-4 sm:p-5',
        'before:absolute before:inset-y-0 before:start-0 before:w-[3px]',
        TONE_EDGE[kpi.tone],
    );

    return kpi.href ? (
        <Link
            href={kpi.href}
            className={cn(
                className,
                'transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
            )}
        >
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

type Attention = {
    key: string;
    rank: 'high' | 'medium' | 'low';
    count?: number;
    href: string;
};

const RANK_DOT: Record<Attention['rank'], string> = {
    high: 'bg-red-500',
    medium: 'bg-amber-500',
    low: 'bg-primary',
};

export default function Dashboard({
    account,
    registration,
    counts,
    month,
    distribution,
    activity,
    recentPermits,
}: Props) {
    const { t } = useTranslation();
    const { auth, today } = usePage().props;
    const isApplicant = registration !== null;
    const isStaff = !isApplicant && auth.user.roles.length > 0;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.title'), href: dashboard() },
    ];

    const kpis: Kpi[] = [];
    const add = (
        key: CountKey,
        icon: Kpi['icon'],
        tone: Tone,
        href?: string,
        warnWhenPositive = false,
    ) => {
        const value = counts[key];

        if (value === undefined) {
            return;
        }

        kpis.push({
            key,
            label: t(`dashboard.kpis.${key}` as TranslationKey),
            value,
            icon,
            tone: warnWhenPositive && value > 0 ? 'warning' : tone,
            href,
        });
    };

    add('vehicles', DeliveryIcon, 'primary', vehicleList.url());
    add(
        'in_force',
        ShieldCheckIcon,
        'success',
        isApplicant ? permitList.url() : undefined,
    );
    add('in_progress', HourglassIcon, 'neutral', permitList.url());
    add('awaiting_payment', WalletMoneyIcon, 'neutral', permitList.url(), true);
    add(
        'waiting_review',
        ClipboardListIcon,
        'primary',
        reviewQueue.url(),
        true,
    );
    add(
        'payments_to_verify',
        Banknote2Icon,
        'primary',
        paymentQueue.url(),
        true,
    );
    add(
        'expiring',
        CalendarMarkIcon,
        'neutral',
        isStaff && month ? reports.url() : undefined,
        true,
    );
    add('scans_today', QrCodeIcon, 'neutral', officerVerify.url());

    if (month) {
        kpis.push(
            {
                key: 'issued_month',
                label: t('dashboard.kpis.issued_month', { month: month.label }),
                value: month.issued,
                icon: DocumentTextIcon,
                tone: 'success',
                href: reports.url(),
            },
            {
                key: 'revenue_month',
                label: t('dashboard.kpis.revenue_month', {
                    month: month.label,
                }),
                value: month.revenue.formatted,
                icon: ChartSquareIcon,
                tone: 'primary',
                href: reports.url(),
            },
        );
    }

    const attention: Attention[] = [
        ...(!account.emailVerified
            ? [
                  {
                      key: 'email_unverified',
                      rank: 'high' as const,
                      href: twoFactor.url(),
                  },
              ]
            : []),
        ...(isStaff && !account.twoFactorEnabled
            ? [
                  {
                      key: 'two_factor_off',
                      rank: 'high' as const,
                      href: twoFactor.url(),
                  },
              ]
            : []),
        ...(registration && !registration.hasOrganization
            ? [
                  {
                      key: 'no_organization',
                      rank: 'high' as const,
                      href: organization.url(),
                  },
              ]
            : []),
        ...(registration &&
        registration.hasOrganization &&
        registration.vehicleCount === 0
            ? [
                  {
                      key: 'no_vehicles',
                      rank: 'medium' as const,
                      href: registerVehicle.url(),
                  },
              ]
            : []),
    ];
    const countItem = (
        key: CountKey,
        rank: Attention['rank'],
        href: string,
    ) => {
        if ((counts[key] ?? 0) > 0) {
            attention.push({ key, rank, count: counts[key], href });
        }
    };

    countItem('changes_requested', 'high', permitList.url());
    countItem('awaiting_payment', 'high', permitList.url());
    countItem('waiting_review', 'medium', reviewQueue.url());
    countItem('payments_to_verify', 'medium', paymentQueue.url());
    countItem(
        'expiring',
        'low',
        isStaff && month ? reports.url() : permitList.url(),
    );

    const total = distribution
        ? PERMIT_STATUSES.reduce((sum, status) => sum + distribution[status], 0)
        : 0;
    const shownStatuses = distribution
        ? PERMIT_STATUSES.filter((status) => distribution[status] > 0)
        : [];

    const actions = isApplicant ? (
        registration?.hasOrganization && (
            <>
                <Button asChild variant="outline">
                    <Link href={registerVehicle()}>
                        {t('dashboard.actions.register_vehicle')}
                    </Link>
                </Button>
                {(counts.vehicles ?? 0) > 0 && (
                    <Button asChild>
                        <Link href={applyForPermit()}>
                            {t('dashboard.actions.apply')}
                        </Link>
                    </Button>
                )}
            </>
        )
    ) : counts.waiting_review !== undefined ? (
        <Button asChild>
            <Link href={reviewQueue()}>{t('dashboard.actions.queue')}</Link>
        </Button>
    ) : counts.payments_to_verify !== undefined ? (
        <Button asChild>
            <Link href={paymentQueue()}>{t('dashboard.actions.payments')}</Link>
        </Button>
    ) : (
        counts.scans_today !== undefined && (
            <Button asChild>
                <Link href={officerVerify()}>
                    {t('dashboard.actions.scan')}
                </Link>
            </Button>
        )
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('dashboard.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('dashboard.greeting', { name: auth.user.name })}
                    description={t('dashboard.intro', {
                        date: `${today.ethiopian_label} (${today.gregorian})`,
                    })}
                    action={actions || undefined}
                />

                {kpis.length > 0 && (
                    <div
                        className={cn(
                            'grid gap-3 sm:grid-cols-2',
                            kpis.length >= 4
                                ? 'xl:grid-cols-4'
                                : 'xl:grid-cols-3',
                        )}
                    >
                        {kpis.map((kpi) => (
                            <KpiCard key={kpi.key} kpi={kpi} />
                        ))}
                    </div>
                )}

                <div className="grid items-start gap-4 xl:grid-cols-2">
                    <section
                        aria-labelledby="attention-title"
                        className="overflow-hidden rounded-panel border border-border bg-card"
                    >
                        <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                            <h2
                                id="attention-title"
                                className="text-base font-semibold"
                            >
                                {t('dashboard.attention.title')}
                            </h2>
                            <span
                                className={cn(
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                    attention.length > 0
                                        ? 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900'
                                        : 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900',
                                )}
                            >
                                {attention.length}
                            </span>
                        </div>
                        {attention.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                {t('dashboard.attention.none')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {attention.map((row) => (
                                    <li
                                        key={row.key}
                                        className="flex items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'size-2 shrink-0 rounded-full',
                                                    RANK_DOT[row.rank],
                                                )}
                                            />
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">
                                                    {t(
                                                        `dashboard.attention.items.${row.key}.label` as TranslationKey,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t(
                                                        `dashboard.attention.items.${row.key}.description` as TranslationKey,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3">
                                            {row.count !== undefined && (
                                                <span className="text-lg font-semibold tabular-nums">
                                                    {row.count}
                                                </span>
                                            )}
                                            <Link
                                                href={row.href}
                                                className="inline-flex items-center gap-1 text-xs font-medium text-primary underline-offset-4 hover:underline"
                                            >
                                                {t('dashboard.attention.open')}
                                                <ArrowRight
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                            </Link>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    {distribution && (
                        <section
                            aria-labelledby="distribution-title"
                            className="overflow-hidden rounded-panel border border-border bg-card"
                        >
                            <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                                <h2
                                    id="distribution-title"
                                    className="text-base font-semibold"
                                >
                                    {t('dashboard.distribution.title')}
                                </h2>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {t('dashboard.distribution.total', {
                                        count: total,
                                    })}
                                </span>
                            </div>
                            {total === 0 ? (
                                <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                    {t('dashboard.distribution.empty')}
                                </p>
                            ) : (
                                <div className="space-y-4 p-4">
                                    <div
                                        aria-hidden="true"
                                        className="flex h-3 overflow-hidden rounded-xs bg-muted"
                                    >
                                        {shownStatuses.map((status) => (
                                            <span
                                                key={status}
                                                className={STATUS_COLOR[status]}
                                                style={{
                                                    width: `${(distribution[status] / total) * 100}%`,
                                                }}
                                            />
                                        ))}
                                    </div>
                                    <ul className="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                                        {shownStatuses.map((status) => (
                                            <li
                                                key={status}
                                                className="flex items-center justify-between gap-3 text-sm"
                                            >
                                                <span className="flex min-w-0 items-center gap-2">
                                                    <span
                                                        aria-hidden="true"
                                                        className={cn(
                                                            'size-2.5 shrink-0 rounded-xs',
                                                            STATUS_COLOR[
                                                                status
                                                            ],
                                                        )}
                                                    />
                                                    <span className="truncate">
                                                        {t(
                                                            `permits.status.${status}`,
                                                        )}
                                                    </span>
                                                </span>
                                                <span className="font-semibold tabular-nums">
                                                    {distribution[status]}
                                                    <span className="ms-1.5 text-xs font-normal text-muted-foreground">
                                                        {Math.round(
                                                            (distribution[
                                                                status
                                                            ] /
                                                                total) *
                                                                100,
                                                        )}
                                                        %
                                                    </span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </section>
                    )}
                </div>

                {recentPermits && (
                    <TableCard
                        title={t('dashboard.recent.title')}
                        titleId="recent-title"
                        actions={
                            <Button asChild variant="outline" size="sm">
                                <Link href={permitList()}>
                                    {t('dashboard.recent.all')}
                                </Link>
                            </Button>
                        }
                    >
                        <Table aria-labelledby="recent-title">
                            <THead>
                                <tr>
                                    <Th>{t('permits.fields.permit_number')}</Th>
                                    <Th>{t('permits.fields.status')}</Th>
                                    <Th>{t('permits.fields.permit_type')}</Th>
                                    <Th>{t('permits.fields.validity')}</Th>
                                </tr>
                            </THead>
                            <TBody>
                                {recentPermits.length === 0 ? (
                                    <EmptyRow colSpan={4}>
                                        {t('dashboard.recent.empty')}
                                    </EmptyRow>
                                ) : (
                                    recentPermits.map((permit) => (
                                        <Tr key={permit.id}>
                                            <Td className="whitespace-nowrap">
                                                <Link
                                                    href={permitPage({
                                                        permit: permit.id,
                                                    })}
                                                    translate="no"
                                                    className="font-semibold text-primary tabular-nums underline-offset-4 hover:underline"
                                                >
                                                    {permit.permit_number ??
                                                        t(
                                                            'permits.not_numbered',
                                                        )}
                                                </Link>
                                            </Td>
                                            <Td>
                                                <StatusBadge
                                                    status={permit.status}
                                                />
                                            </Td>
                                            <Td className="whitespace-nowrap">
                                                {t(
                                                    `permits.types.${permit.permit_type}`,
                                                )}
                                            </Td>
                                            <Td className="whitespace-nowrap">
                                                <span className="flex flex-wrap items-baseline gap-x-1.5">
                                                    <DualDate
                                                        date={permit.valid_from}
                                                    />
                                                    <span aria-hidden="true">
                                                        –
                                                    </span>
                                                    <DualDate
                                                        date={permit.valid_to}
                                                    />
                                                </span>
                                            </Td>
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>
                )}

                {activity && (
                    <TableCard
                        title={t('dashboard.activity.title')}
                        titleId="activity-title"
                    >
                        <Table aria-labelledby="activity-title">
                            <THead>
                                <tr>
                                    <Th>{t('dashboard.activity.when')}</Th>
                                    <Th>{t('dashboard.activity.permit')}</Th>
                                    {isStaff && (
                                        <Th>
                                            {t(
                                                'dashboard.activity.organization',
                                            )}
                                        </Th>
                                    )}
                                    <Th>{t('dashboard.activity.what')}</Th>
                                    {isStaff && (
                                        <Th>{t('dashboard.activity.by')}</Th>
                                    )}
                                </tr>
                            </THead>
                            <TBody>
                                {activity.length === 0 ? (
                                    <EmptyRow colSpan={isStaff ? 5 : 3}>
                                        {t('dashboard.activity.empty')}
                                    </EmptyRow>
                                ) : (
                                    activity.map((step) => (
                                        <Tr key={step.id}>
                                            <Td className="whitespace-nowrap">
                                                <DualDate date={step.at} />
                                                <span className="ms-2 text-xs text-muted-foreground tabular-nums">
                                                    {step.time}
                                                </span>
                                            </Td>
                                            <Td className="whitespace-nowrap">
                                                <Link
                                                    href={
                                                        step.staff_link
                                                            ? staffPermitPage({
                                                                  permit: step.permit_id,
                                                              })
                                                            : permitPage({
                                                                  permit: step.permit_id,
                                                              })
                                                    }
                                                    translate="no"
                                                    className="font-semibold text-primary tabular-nums underline-offset-4 hover:underline"
                                                >
                                                    {step.reference}
                                                </Link>
                                            </Td>
                                            {isStaff && (
                                                <Td>{step.organization}</Td>
                                            )}
                                            <Td>
                                                {step.from === null ? (
                                                    <span className="font-medium">
                                                        {t(
                                                            'dashboard.activity.created',
                                                        )}
                                                    </span>
                                                ) : step.from === step.to ? (
                                                    <span className="font-medium">
                                                        {t(
                                                            'dashboard.activity.reissued',
                                                        )}
                                                    </span>
                                                ) : (
                                                    <StatusBadge
                                                        status={step.to}
                                                    />
                                                )}
                                            </Td>
                                            {isStaff && (
                                                <Td muted>
                                                    {step.actor ?? '—'}
                                                </Td>
                                            )}
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>
                )}
            </div>
        </AppLayout>
    );
}
