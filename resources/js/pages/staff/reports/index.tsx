import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
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

/**
 * A bar whose length shows a share of the largest value. Decorative: the
 * number beside it carries the information.
 */
function Bar({ value, max }: { value: number; max: number }) {
    const width =
        max > 0 ? Math.max((value / max) * 100, value > 0 ? 2 : 0) : 0;

    return (
        <span
            aria-hidden="true"
            className="block h-2 min-w-24 rounded-xs bg-muted"
        >
            <span
                className="block h-full rounded-xs bg-primary"
                style={{ width: `${width}%` }}
            />
        </span>
    );
}

function Tile({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-card border border-border bg-card p-5">
            <p className="text-eyebrow text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-bold tabular-nums">{value}</p>
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

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
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

                <TableCard title={t('reports.monthly')} titleId="monthly-title">
                    <Table aria-labelledby="monthly-title">
                        <THead>
                            <tr>
                                <Th>{t('reports.month')}</Th>
                                <Th align="right">{t('reports.issued')}</Th>
                                <Th className="w-1/3">
                                    <span className="sr-only">
                                        {t('reports.issued')}
                                    </span>
                                </Th>
                                <Th align="right">{t('reports.revenue')}</Th>
                                <Th className="w-1/3">
                                    <span className="sr-only">
                                        {t('reports.revenue')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {monthly.map((row) => (
                                <Tr key={row.key}>
                                    <Td className="font-medium whitespace-nowrap">
                                        {row.label}
                                    </Td>
                                    <Td numeric className="font-semibold">
                                        {row.issued}
                                    </Td>
                                    <Td>
                                        <Bar
                                            value={row.issued}
                                            max={maxIssued}
                                        />
                                    </Td>
                                    <Td
                                        numeric
                                        className="font-semibold whitespace-nowrap"
                                    >
                                        {row.revenue.formatted}
                                    </Td>
                                    <Td>
                                        <Bar
                                            value={row.revenue.amount_cents}
                                            max={maxRevenue}
                                        />
                                    </Td>
                                </Tr>
                            ))}
                        </TBody>
                    </Table>
                </TableCard>

                <div className="grid gap-6 xl:grid-cols-2">
                    <TableCard
                        title={t('reports.by_status')}
                        titleId="status-title"
                    >
                        <Table aria-labelledby="status-title">
                            <THead>
                                <tr>
                                    <Th>{t('common.status')}</Th>
                                    <Th align="right">
                                        {t('reports.permits')}
                                    </Th>
                                    <Th className="w-2/5">
                                        <span className="sr-only">
                                            {t('reports.permits')}
                                        </span>
                                    </Th>
                                </tr>
                            </THead>
                            <TBody>
                                {STATUSES.map((status) => (
                                    <Tr key={status}>
                                        <Td>
                                            <StatusBadge status={status} />
                                        </Td>
                                        <Td numeric className="font-semibold">
                                            {byStatus[status]}
                                        </Td>
                                        <Td>
                                            <Bar
                                                value={byStatus[status]}
                                                max={maxStatus}
                                            />
                                        </Td>
                                    </Tr>
                                ))}
                            </TBody>
                        </Table>
                    </TableCard>

                    <TableCard
                        title={t('reports.by_vehicle_type')}
                        titleId="types-title"
                    >
                        <Table aria-labelledby="types-title">
                            <THead>
                                <tr>
                                    <Th>{t('reports.vehicle_type')}</Th>
                                    <Th align="right">
                                        {t('reports.permits')}
                                    </Th>
                                    <Th className="w-2/5">
                                        <span className="sr-only">
                                            {t('reports.permits')}
                                        </span>
                                    </Th>
                                </tr>
                            </THead>
                            <TBody>
                                {byVehicleType.length === 0 ? (
                                    <EmptyRow colSpan={3}>
                                        {t('reports.none')}
                                    </EmptyRow>
                                ) : (
                                    byVehicleType.map((row) => (
                                        <Tr key={row.name}>
                                            <Td className="font-medium">
                                                {row.name}
                                            </Td>
                                            <Td
                                                numeric
                                                className="font-semibold"
                                            >
                                                {row.permits}
                                            </Td>
                                            <Td>
                                                <Bar
                                                    value={row.permits}
                                                    max={maxType}
                                                />
                                            </Td>
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>
                </div>

                <TableCard
                    title={t('reports.top_organizations')}
                    titleId="organizations-title"
                >
                    <Table aria-labelledby="organizations-title">
                        <THead>
                            <tr>
                                <Th>{t('reports.organization')}</Th>
                                <Th align="right">{t('reports.permits')}</Th>
                                <Th className="w-1/3">
                                    <span className="sr-only">
                                        {t('reports.permits')}
                                    </span>
                                </Th>
                                <Th align="right">{t('reports.revenue')}</Th>
                            </tr>
                        </THead>
                        <TBody>
                            {topOrganizations.length === 0 ? (
                                <EmptyRow colSpan={4}>
                                    {t('reports.none')}
                                </EmptyRow>
                            ) : (
                                topOrganizations.map((row) => (
                                    <Tr key={row.name}>
                                        <Td className="font-medium">
                                            {row.name}
                                        </Td>
                                        <Td numeric className="font-semibold">
                                            {row.permits}
                                        </Td>
                                        <Td>
                                            <Bar
                                                value={row.permits}
                                                max={maxOrganization}
                                            />
                                        </Td>
                                        <Td
                                            numeric
                                            className="font-semibold whitespace-nowrap"
                                        >
                                            {row.revenue.formatted}
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>

                <TableCard
                    title={t('reports.expiring', { days: expiring.days })}
                    titleId="expiring-title"
                    footer={
                        expiring.count > expiring.permits.length ? (
                            <p className="text-sm text-muted-foreground">
                                {t('reports.expiring_more', {
                                    count:
                                        expiring.count -
                                        expiring.permits.length,
                                })}
                            </p>
                        ) : undefined
                    }
                >
                    <Table aria-labelledby="expiring-title">
                        <THead>
                            <tr>
                                <Th>{t('reports.permit_number')}</Th>
                                <Th>{t('reports.plate')}</Th>
                                <Th>{t('reports.organization')}</Th>
                                <Th>{t('reports.valid_to')}</Th>
                            </tr>
                        </THead>
                        <TBody>
                            {expiring.count === 0 ? (
                                <EmptyRow colSpan={4}>
                                    {t('reports.expiring_none', {
                                        days: expiring.days,
                                    })}
                                </EmptyRow>
                            ) : (
                                expiring.permits.map((permit) => (
                                    <Tr key={permit.id}>
                                        <Td className="whitespace-nowrap">
                                            <Link
                                                href={permitPage({
                                                    permit: permit.id,
                                                })}
                                                translate="no"
                                                className="font-semibold text-primary tabular-nums underline-offset-4 hover:underline"
                                            >
                                                {permit.permit_number}
                                            </Link>
                                        </Td>
                                        <Td>
                                            {permit.plate ? (
                                                <PlateChip
                                                    plate={permit.plate}
                                                />
                                            ) : (
                                                '—'
                                            )}
                                        </Td>
                                        <Td className="font-medium">
                                            {permit.organization}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <DualDate date={permit.valid_to} />
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>
            </div>
        </AppLayout>
    );
}
