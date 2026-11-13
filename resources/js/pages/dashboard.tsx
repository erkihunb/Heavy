import { Head, Link, usePage } from '@inertiajs/react';
import { Banknote2Icon } from '@solar-icons/react/linear/banknote-2';
import { CalendarMarkIcon } from '@solar-icons/react/linear/calendar-mark';
import { ChartSquareIcon } from '@solar-icons/react/linear/chart-square';
import { ClipboardListIcon } from '@solar-icons/react/linear/clipboard-list';
import { DocumentTextIcon } from '@solar-icons/react/linear/document-text';
import { QrCodeIcon } from '@solar-icons/react/linear/qr-code';
import { ShieldCheckIcon } from '@solar-icons/react/linear/shield-check';
import { WalletMoneyIcon } from '@solar-icons/react/linear/wallet-money';
import { ArrowDown, ArrowRight, ArrowUp, Minus } from 'lucide-react';
import type { ComponentType } from 'react';
import {
    AreaChart,
    ChartCard,
    ColumnChart,
    compactNumber,
    Meter,
    SegmentedBar,
    Sparkline,
} from '@/components/charts';
import type { ChartPoint } from '@/components/charts';
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
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { verify as officerVerify } from '@/routes/officer';
import { index as paymentQueue } from '@/routes/staff/payments';
import {
    index as reviewQueue,
    show as staffPermitPage,
} from '@/routes/staff/permits';
import { index as reports } from '@/routes/staff/reports';
import { show as twoFactor } from '@/routes/two-factor';
import PageHeader from '@/shell/Components/PageHeader';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import type { DualDate as DualDateValue, Money } from '@/types';
import { PERMIT_STATUSES } from '@/types/enums';
import type { PaymentStatus, PermitStatus, ScanResult } from '@/types/enums';
import type { TranslationKey } from '@/types/translations';

type CountKey =
    | 'in_force'
    | 'waiting_review'
    | 'payments_to_verify'
    | 'expiring'
    | 'to_collect'
    | 'to_issue'
    | 'scans_today';

type Props = {
    account: {
        emailVerified: boolean;
        twoFactorEnabled: boolean;
        /** Does the office's work (by permissions): asked to turn two-factor on. */
        staff: boolean;
    };
    counts: Partial<Record<CountKey, number>>;
    month: {
        label: string;
        issued: number;
        revenue: Money;
        revenue_cents: number;
        previous: {
            label: string;
            issued: number;
            revenue: Money;
            revenue_cents: number;
        } | null;
    } | null;
    insights: Insights;
    distribution: Record<PermitStatus, number> | null;
    activity:
        | {
              id: number;
              permit_id: string;
              reference: string;
              organization: string;
              from: PermitStatus | null;
              to: PermitStatus;
              at: DualDateValue;
              time: string;
              actor: string | null;
          }[]
        | null;
};

type DayCount = {
    date: string;
    label: string;
    gregorian: string;
    count: number;
};

type Insights = {
    /** This Ethiopian month, e.g. "ጥቅምት 2019". */
    month: string;
    trend:
        | {
              key: string;
              label: string;
              short: string;
              issued: number;
              revenue: Money;
          }[]
        | null;
    submissions: DayCount[] | null;
    decisions: {
        approved: number;
        changes_requested: number;
        rejected: number;
    } | null;
    payments: Record<PaymentStatus, { count: number; amount: Money }> | null;
    scans: { today: Record<ScanResult, number>; days: DayCount[] } | null;
};

/* Scan results keep their meaning: good, warning, serious, critical or neutral. */
const SCAN_COLOR: Record<ScanResult, string> = {
    valid: 'bg-emerald-600 dark:bg-emerald-500',
    outside_hours: 'bg-amber-500',
    not_yet_valid: 'bg-sky-500',
    expired: 'bg-zinc-400 dark:bg-zinc-500',
    revoked: 'bg-red-600',
    not_found: 'bg-orange-400',
};

const SCAN_ORDER: ScanResult[] = [
    'valid',
    'outside_hours',
    'not_yet_valid',
    'expired',
    'revoked',
    'not_found',
];

const byDay = (rows: DayCount[], locale: string): ChartPoint[] =>
    rows.map((row) => {
        const split = row.label.lastIndexOf(' ');
        const day = row.label.slice(split + 1);

        return {
            key: row.date,
            label: `${row.label} (${row.gregorian})`,
            short: day === '1' ? row.label : day,
            value: row.count,
            display: compactNumber(row.count, locale),
        };
    });

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
    payment_confirmed: 'bg-teal-500',
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
    /** What the number counts, in a sentence. */
    hint?: string;
    /** Change from the previous period. */
    delta?: { direction: 'up' | 'down' | 'same'; text: string };
    /** Recent periods, oldest first, for a sparkline. */
    trend?: number[];
    icon: ComponentType<{ className?: string }>;
    tone: Tone;
    href?: string;
};

const DELTA_STYLE = {
    up: { icon: ArrowUp, className: 'text-emerald-700 dark:text-emerald-400' },
    down: { icon: ArrowDown, className: 'text-red-700 dark:text-red-400' },
    same: { icon: Minus, className: 'text-muted-foreground' },
} as const;

function Delta({ delta }: { delta: NonNullable<Kpi['delta']> }) {
    const { icon: Icon, className } = DELTA_STYLE[delta.direction];

    return (
        <p
            className={cn(
                'mt-1.5 flex items-center gap-1 text-xs font-medium',
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-3.5 shrink-0" />
            {delta.text}
        </p>
    );
}

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
            <p className="pt-3 text-2xl leading-tight font-semibold tracking-tight sm:text-3xl">
                {kpi.value}
            </p>
            {(kpi.delta || kpi.trend) && (
                <div className="flex items-end justify-between gap-3">
                    {kpi.delta ? <Delta delta={kpi.delta} /> : <span />}
                    {kpi.trend && <Sparkline values={kpi.trend} />}
                </div>
            )}
            {kpi.hint && (
                <p className="mt-auto pt-2 text-xs leading-relaxed text-muted-foreground">
                    {kpi.hint}
                </p>
            )}
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
    counts,
    month,
    insights,
    distribution,
    activity,
}: Props) {
    const { t } = useTranslation();
    const { auth, today, locale } = usePage().props;

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
            hint: t(`dashboard.hints.${key}` as TranslationKey),
            value,
            icon,
            tone: warnWhenPositive && value > 0 ? 'warning' : tone,
            href,
        });
    };

    add('in_force', ShieldCheckIcon, 'success');
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
        month ? reports.url() : undefined,
        true,
    );
    add(
        'to_collect',
        WalletMoneyIcon,
        'primary',
        reviewQueue.url({ query: { status: 'awaiting_payment' } }),
        true,
    );
    add(
        'to_issue',
        DocumentTextIcon,
        'primary',
        reviewQueue.url({ query: { status: 'payment_confirmed' } }),
        true,
    );
    add('scans_today', QrCodeIcon, 'neutral', officerVerify.url());

    const delta = (
        now: number,
        before: number,
        shown: string,
        previous: string,
    ): Kpi['delta'] =>
        now === before
            ? {
                  direction: 'same',
                  text: t('dashboard.delta.same', { month: previous }),
              }
            : {
                  direction: now > before ? 'up' : 'down',
                  text: t(
                      now > before
                          ? 'dashboard.delta.up'
                          : 'dashboard.delta.down',
                      { value: shown, month: previous },
                  ),
              };

    if (month) {
        const previous = month.previous;
        const birr = (cents: number) =>
            new Intl.NumberFormat('en', { maximumFractionDigits: 2 }).format(
                cents / 100,
            );

        kpis.push(
            {
                key: 'issued_month',
                label: t('dashboard.kpis.issued_month', { month: month.label }),
                hint: t('dashboard.hints.issued_month'),
                delta: previous
                    ? delta(
                          month.issued,
                          previous.issued,
                          String(Math.abs(month.issued - previous.issued)),
                          previous.label,
                      )
                    : undefined,
                trend: insights.trend?.map((row) => row.issued),
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
                hint: t('dashboard.hints.revenue_month'),
                delta: previous
                    ? delta(
                          month.revenue_cents,
                          previous.revenue_cents,
                          `ETB ${birr(Math.abs(month.revenue_cents - previous.revenue_cents))}`,
                          previous.label,
                      )
                    : undefined,
                trend: insights.trend?.map((row) => row.revenue.amount_cents),
                value: month.revenue.formatted,
                icon: ChartSquareIcon,
                tone: 'primary',
                href: reports.url(),
            },
        );
    }

    const tick = (value: number) => compactNumber(value, locale);
    const issuedPoints: ChartPoint[] =
        insights.trend?.map((row) => ({
            key: row.key,
            label: row.label,
            short: row.short,
            value: row.issued,
            display: String(row.issued),
        })) ?? [];
    const revenuePoints: ChartPoint[] =
        insights.trend?.map((row) => ({
            key: row.key,
            label: row.label,
            short: row.short,
            value: row.revenue.amount_cents / 100,
            display: row.revenue.formatted,
        })) ?? [];
    const submitted = insights.submissions ?? [];
    const submittedTotal = submitted.reduce((sum, row) => sum + row.count, 0);
    const decisions = insights.decisions;
    const decided = decisions
        ? decisions.approved + decisions.changes_requested + decisions.rejected
        : 0;
    const payments = insights.payments;
    const paymentCount = payments
        ? payments.verified.count +
          payments.submitted.count +
          payments.rejected.count
        : 0;
    const paymentAmount = payments
        ? payments.verified.amount.amount_cents +
          payments.submitted.amount.amount_cents +
          payments.rejected.amount.amount_cents
        : 0;
    const scansToday = insights.scans
        ? SCAN_ORDER.reduce((sum, r) => sum + insights.scans!.today[r], 0)
        : 0;
    const sideCard = decisions !== null;

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
        ...(account.staff && !account.twoFactorEnabled
            ? [
                  {
                      key: 'two_factor_off',
                      rank: 'high' as const,
                      href: twoFactor.url(),
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

    countItem('waiting_review', 'medium', reviewQueue.url());
    countItem('payments_to_verify', 'medium', paymentQueue.url());
    countItem(
        'to_collect',
        'medium',
        reviewQueue.url({ query: { status: 'awaiting_payment' } }),
    );
    countItem(
        'to_issue',
        'medium',
        reviewQueue.url({ query: { status: 'payment_confirmed' } }),
    );
    if (month) {
        countItem('expiring', 'low', reports.url());
    }

    const total = distribution
        ? PERMIT_STATUSES.reduce((sum, status) => sum + distribution[status], 0)
        : 0;
    const shownStatuses = distribution
        ? PERMIT_STATUSES.filter((status) => distribution[status] > 0)
        : [];

    const actions =
        counts.waiting_review !== undefined ? (
            <Button asChild>
                <Link href={reviewQueue()}>{t('dashboard.actions.queue')}</Link>
            </Button>
        ) : counts.payments_to_verify !== undefined ? (
            <Button asChild>
                <Link href={paymentQueue()}>
                    {t('dashboard.actions.payments')}
                </Link>
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
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('dashboard.greeting', { name: auth.user.name })}
                    description={t('dashboard.intro', {
                        date: `${today.ethiopian_label} (${today.gregorian})`,
                    })}
                    actions={actions || undefined}
                />
            }
        >
            <Head title={t('dashboard.title')} />

            <div className="flex flex-col gap-6">
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

                {insights.trend && (
                    <div className="grid gap-4 xl:grid-cols-2">
                        <ChartCard
                            title={t('dashboard.charts.issued.title')}
                            description={t(
                                'dashboard.charts.issued.description',
                                { count: issuedPoints.length },
                            )}
                        >
                            <ColumnChart
                                data={issuedPoints}
                                caption={t('dashboard.charts.issued.title')}
                                periodLabel={t('dashboard.charts.period')}
                                valueLabel={t('dashboard.charts.count')}
                                formatTick={tick}
                            />
                        </ChartCard>
                        <ChartCard
                            title={t('dashboard.charts.revenue.title')}
                            description={t(
                                'dashboard.charts.revenue.description',
                            )}
                        >
                            <AreaChart
                                data={revenuePoints}
                                caption={t('dashboard.charts.revenue.title')}
                                periodLabel={t('dashboard.charts.period')}
                                valueLabel={t('dashboard.charts.amount')}
                                formatTick={tick}
                            />
                        </ChartCard>
                    </div>
                )}

                {(insights.submissions || sideCard) && (
                    <div className="grid items-stretch gap-4 xl:grid-cols-3">
                        {insights.submissions && (
                            <ChartCard
                                className={
                                    sideCard ? 'xl:col-span-2' : 'xl:col-span-3'
                                }
                                title={t('dashboard.charts.submissions.title')}
                                description={t(
                                    'dashboard.charts.submissions.description',
                                    { count: submitted.length },
                                )}
                                aside={
                                    <span className="text-xs text-muted-foreground">
                                        {t(
                                            'dashboard.charts.submissions.total',
                                            {
                                                count: submittedTotal,
                                                days: submitted.length,
                                            },
                                        )}
                                    </span>
                                }
                            >
                                <AreaChart
                                    data={byDay(submitted, locale)}
                                    caption={t(
                                        'dashboard.charts.submissions.title',
                                    )}
                                    periodLabel={t('dashboard.charts.period')}
                                    valueLabel={t('dashboard.charts.count')}
                                    formatTick={tick}
                                />
                            </ChartCard>
                        )}

                        {decisions && (
                            <ChartCard
                                title={t('dashboard.charts.decisions.title', {
                                    month: insights.month,
                                })}
                                description={t(
                                    'dashboard.charts.decisions.description',
                                )}
                            >
                                {decided === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        {t('dashboard.charts.decisions.empty')}
                                    </p>
                                ) : (
                                    <div className="space-y-5">
                                        <Meter
                                            label={t(
                                                'dashboard.charts.decisions.rate',
                                            )}
                                            percent={
                                                (decisions.approved / decided) *
                                                100
                                            }
                                            tone="success"
                                            caption={t(
                                                'dashboard.charts.decisions.of',
                                                {
                                                    count: decisions.approved,
                                                    total: decided,
                                                },
                                            )}
                                        />
                                        <SegmentedBar
                                            shareLabel={t(
                                                'dashboard.charts.share',
                                            )}
                                            parts={[
                                                {
                                                    key: 'approved',
                                                    label: t(
                                                        'dashboard.charts.decisions.approved',
                                                    ),
                                                    value: decisions.approved,
                                                    color: 'bg-emerald-600 dark:bg-emerald-500',
                                                },
                                                {
                                                    key: 'changes_requested',
                                                    label: t(
                                                        'dashboard.charts.decisions.changes_requested',
                                                    ),
                                                    value: decisions.changes_requested,
                                                    color: 'bg-amber-500',
                                                },
                                                {
                                                    key: 'rejected',
                                                    label: t(
                                                        'dashboard.charts.decisions.rejected',
                                                    ),
                                                    value: decisions.rejected,
                                                    color: 'bg-red-600',
                                                },
                                            ]}
                                        />
                                    </div>
                                )}
                            </ChartCard>
                        )}
                    </div>
                )}

                {(payments || insights.scans) && (
                    <div className="grid items-stretch gap-4 xl:grid-cols-2">
                        {payments && (
                            <ChartCard
                                title={t('dashboard.charts.payments.title', {
                                    month: insights.month,
                                })}
                                description={t(
                                    'dashboard.charts.payments.description',
                                )}
                            >
                                {paymentCount === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        {t('dashboard.charts.payments.empty')}
                                    </p>
                                ) : (
                                    <div className="space-y-5">
                                        <Meter
                                            label={t(
                                                'dashboard.charts.payments.collected',
                                            )}
                                            value={
                                                payments.verified.amount
                                                    .formatted
                                            }
                                            percent={
                                                paymentAmount === 0
                                                    ? 0
                                                    : (payments.verified.amount
                                                          .amount_cents /
                                                          paymentAmount) *
                                                      100
                                            }
                                            tone="success"
                                        />
                                        <SegmentedBar
                                            shareLabel={t(
                                                'dashboard.charts.share',
                                            )}
                                            parts={[
                                                {
                                                    key: 'verified',
                                                    label: t(
                                                        'dashboard.charts.payments.verified',
                                                    ),
                                                    value: payments.verified
                                                        .count,
                                                    color: 'bg-emerald-600 dark:bg-emerald-500',
                                                },
                                                {
                                                    key: 'submitted',
                                                    label: t(
                                                        'dashboard.charts.payments.submitted',
                                                    ),
                                                    value: payments.submitted
                                                        .count,
                                                    color: 'bg-sky-500',
                                                },
                                                {
                                                    key: 'rejected',
                                                    label: t(
                                                        'dashboard.charts.payments.rejected',
                                                    ),
                                                    value: payments.rejected
                                                        .count,
                                                    color: 'bg-red-600',
                                                },
                                            ]}
                                        />
                                    </div>
                                )}
                            </ChartCard>
                        )}

                        {insights.scans && (
                            <ChartCard
                                title={t('dashboard.charts.scans.title')}
                                description={t(
                                    'dashboard.charts.scans.description',
                                )}
                                aside={
                                    <span className="text-2xl font-semibold">
                                        {scansToday}
                                    </span>
                                }
                            >
                                <div className="space-y-5">
                                    {scansToday === 0 ? (
                                        <p className="py-2 text-sm text-muted-foreground">
                                            {t('dashboard.charts.scans.empty')}
                                        </p>
                                    ) : (
                                        <SegmentedBar
                                            shareLabel={t(
                                                'dashboard.charts.share',
                                            )}
                                            parts={SCAN_ORDER.map((result) => ({
                                                key: result,
                                                label: t(
                                                    `verification.results.${result}.title`,
                                                ),
                                                value: insights.scans!.today[
                                                    result
                                                ],
                                                color: SCAN_COLOR[result],
                                            }))}
                                        />
                                    )}
                                    <div>
                                        <p className="mb-2 text-xs font-medium text-muted-foreground">
                                            {t('dashboard.charts.scans.week', {
                                                count: insights.scans.days
                                                    .length,
                                            })}
                                        </p>
                                        <ColumnChart
                                            data={byDay(
                                                insights.scans.days,
                                                locale,
                                            )}
                                            caption={t(
                                                'dashboard.charts.scans.week',
                                                {
                                                    count: insights.scans.days
                                                        .length,
                                                },
                                            )}
                                            periodLabel={t(
                                                'dashboard.charts.period',
                                            )}
                                            valueLabel={t(
                                                'dashboard.charts.count',
                                            )}
                                            formatTick={tick}
                                        />
                                    </div>
                                </div>
                            </ChartCard>
                        )}
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
                                    <Th>
                                        {t('dashboard.activity.organization')}
                                    </Th>
                                    <Th>{t('dashboard.activity.what')}</Th>
                                    <Th>{t('dashboard.activity.by')}</Th>
                                </tr>
                            </THead>
                            <TBody>
                                {activity.length === 0 ? (
                                    <EmptyRow colSpan={5}>
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
                                                    href={staffPermitPage({
                                                        permit: step.permit_id,
                                                    })}
                                                    translate="no"
                                                    className="font-semibold text-primary tabular-nums underline-offset-4 hover:underline"
                                                >
                                                    {step.reference}
                                                </Link>
                                            </Td>
                                            <Td>{step.organization}</Td>
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
                                            <Td muted>{step.actor ?? '—'}</Td>
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
