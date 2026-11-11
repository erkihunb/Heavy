import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import type { ReactNode } from 'react';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { PlateChip } from '@/components/plate-chip';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index as exports } from '@/routes/staff/exports';
import { show as permitPage } from '@/routes/staff/permits';
import { index } from '@/routes/staff/reports';
import type { BreadcrumbItem, DualDate as DualDateValue, Money } from '@/types';
import { PERMIT_STATUSES } from '@/types/enums';
import type { PermitStatus } from '@/types/enums';

type Props = {
    period: { from: string; to: string };
    summary: {
        month: string;
        issued_this_month: number;
        revenue_this_month: Money;
        issued_period: number;
        revenue_period: Money;
    };
    byStatus: Record<PermitStatus, number>;
    monthly: { key: string; label: string; issued: number; revenue: Money }[];
    byVehicleType: { name: string; permits: number }[];
    topOrganizations: { name: string; permits: number; revenue: Money }[];
    expiring: {
        days: number;
        count: number;
        permits: {
            id: string;
            permit_number: string | null;
            organization: string;
            plate: string | null;
            valid_to: DualDateValue;
        }[];
    };
};

/** Statuses worth showing on the dashboard: everything staff ever see. */
const STATUSES = PERMIT_STATUSES.filter(
    (status) => status !== 'draft' && status !== 'withdrawn',
);

function Card({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={id}
            className="space-y-4 rounded-2xl border border-border bg-card p-5 sm:p-6"
        >
            <h2 id={id} className="text-lg font-bold">
                {title}
            </h2>
            {children}
        </section>
    );
}

/**
 * A bar whose length shows a share of the largest value. Decorative: the
 * number next to it carries the information.
 */
function Bar({ value, max }: { value: number; max: number }) {
    const width =
        max > 0 ? Math.max((value / max) * 100, value > 0 ? 2 : 0) : 0;

    return (
        <span aria-hidden="true" className="block h-2.5 rounded-full bg-muted">
            <span
                className="block h-full rounded-full bg-primary"
                style={{ width: `${width}%` }}
            />
        </span>
    );
}

function Tile({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-2xl border border-border bg-card p-5">
            <p className="text-eyebrow text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-extrabold tabular-nums sm:text-3xl">
                {value}
            </p>
        </div>
    );
}

export default function Reports({
    period,
    summary,
    byStatus,
    monthly,
    byVehicleType,
    topOrganizations,
    expiring,
}: Props) {
    const { t } = useTranslation();

    const maxIssued = Math.max(0, ...monthly.map((row) => row.issued));
    const maxRevenue = Math.max(
        0,
        ...monthly.map((row) => row.revenue.amount_cents),
    );
    const maxStatus = Math.max(
        0,
        ...STATUSES.map((status) => byStatus[status]),
    );
    const maxType = Math.max(0, ...byVehicleType.map((row) => row.permits));
    const maxOrganization = Math.max(
        0,
        ...topOrganizations.map((row) => row.permits),
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('reports.title'), href: index() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('reports.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('reports.title')}
                    description={t('reports.intro', period)}
                    action={
                        <Button asChild variant="outline">
                            <Link href={exports()}>
                                <Download aria-hidden="true" />
                                {t('reports.exports_link')}
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Tile
                        label={t('reports.summary.issued_this_month', {
                            month: summary.month,
                        })}
                        value={summary.issued_this_month}
                    />
                    <Tile
                        label={t('reports.summary.revenue_this_month', {
                            month: summary.month,
                        })}
                        value={summary.revenue_this_month.formatted}
                    />
                    <Tile
                        label={t('reports.summary.issued_period')}
                        value={summary.issued_period}
                    />
                    <Tile
                        label={t('reports.summary.revenue_period')}
                        value={summary.revenue_period.formatted}
                    />
                </div>

                <Card id="monthly-title" title={t('reports.monthly')}>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[32rem] text-left text-sm">
                            <thead>
                                <tr className="text-eyebrow text-muted-foreground">
                                    <th scope="col" className="py-2 pr-4">
                                        {t('reports.month')}
                                    </th>
                                    <th scope="col" className="w-2/5 py-2 pr-4">
                                        {t('reports.issued')}
                                    </th>
                                    <th scope="col" className="w-2/5 py-2">
                                        {t('reports.revenue')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {monthly.map((row) => (
                                    <tr key={row.key}>
                                        <th
                                            scope="row"
                                            className="py-2 pr-4 font-medium whitespace-nowrap"
                                        >
                                            {row.label}
                                        </th>
                                        <td className="py-2 pr-4">
                                            <span className="grid grid-cols-[3rem_1fr] items-center gap-2">
                                                <span className="text-right font-bold tabular-nums">
                                                    {row.issued}
                                                </span>
                                                <Bar
                                                    value={row.issued}
                                                    max={maxIssued}
                                                />
                                            </span>
                                        </td>
                                        <td className="py-2">
                                            <span className="grid grid-cols-[8rem_1fr] items-center gap-2">
                                                <span className="text-right font-bold whitespace-nowrap tabular-nums">
                                                    {row.revenue.formatted}
                                                </span>
                                                <Bar
                                                    value={
                                                        row.revenue.amount_cents
                                                    }
                                                    max={maxRevenue}
                                                />
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card id="status-title" title={t('reports.by_status')}>
                        <ul className="space-y-3">
                            {STATUSES.map((status) => (
                                <li
                                    key={status}
                                    className="grid grid-cols-[minmax(0,11rem)_3rem_1fr] items-center gap-3"
                                >
                                    <StatusBadge status={status} />
                                    <span className="text-right font-bold tabular-nums">
                                        {byStatus[status]}
                                    </span>
                                    <Bar
                                        value={byStatus[status]}
                                        max={maxStatus}
                                    />
                                </li>
                            ))}
                        </ul>
                    </Card>

                    <Card id="types-title" title={t('reports.by_vehicle_type')}>
                        {byVehicleType.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('reports.none')}
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {byVehicleType.map((row) => (
                                    <li
                                        key={row.name}
                                        className="grid grid-cols-[minmax(0,11rem)_3rem_1fr] items-center gap-3"
                                    >
                                        <span className="truncate font-medium">
                                            {row.name}
                                        </span>
                                        <span className="text-right font-bold tabular-nums">
                                            {row.permits}
                                        </span>
                                        <Bar
                                            value={row.permits}
                                            max={maxType}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                <Card
                    id="organizations-title"
                    title={t('reports.top_organizations')}
                >
                    {topOrganizations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('reports.none')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[32rem] text-left text-sm">
                                <thead>
                                    <tr className="text-eyebrow text-muted-foreground">
                                        <th scope="col" className="py-2 pr-4">
                                            {t('reports.organization')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="w-2/5 py-2 pr-4"
                                        >
                                            {t('reports.permits')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right"
                                        >
                                            {t('reports.revenue')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {topOrganizations.map((row) => (
                                        <tr key={row.name}>
                                            <th
                                                scope="row"
                                                className="py-2 pr-4 font-medium"
                                            >
                                                {row.name}
                                            </th>
                                            <td className="py-2 pr-4">
                                                <span className="grid grid-cols-[3rem_1fr] items-center gap-2">
                                                    <span className="text-right font-bold tabular-nums">
                                                        {row.permits}
                                                    </span>
                                                    <Bar
                                                        value={row.permits}
                                                        max={maxOrganization}
                                                    />
                                                </span>
                                            </td>
                                            <td className="py-2 text-right font-bold whitespace-nowrap tabular-nums">
                                                {row.revenue.formatted}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Card
                    id="expiring-title"
                    title={t('reports.expiring', { days: expiring.days })}
                >
                    {expiring.count === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('reports.expiring_none', {
                                days: expiring.days,
                            })}
                        </p>
                    ) : (
                        <>
                            <ul className="divide-y divide-border">
                                {expiring.permits.map((permit) => (
                                    <li
                                        key={permit.id}
                                        className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3"
                                    >
                                        <Link
                                            href={permitPage({
                                                permit: permit.id,
                                            })}
                                            translate="no"
                                            className="font-bold tabular-nums underline underline-offset-4"
                                        >
                                            {permit.permit_number}
                                        </Link>
                                        {permit.plate && (
                                            <PlateChip plate={permit.plate} />
                                        )}
                                        <span className="font-medium">
                                            {permit.organization}
                                        </span>
                                        <span className="flex items-baseline gap-2 text-sm">
                                            <span className="text-muted-foreground">
                                                {t('reports.valid_to')}
                                            </span>
                                            <DualDate date={permit.valid_to} />
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            {expiring.count > expiring.permits.length && (
                                <p className="text-sm text-muted-foreground">
                                    {t('reports.expiring_more', {
                                        count:
                                            expiring.count -
                                            expiring.permits.length,
                                    })}
                                </p>
                            )}
                        </>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
