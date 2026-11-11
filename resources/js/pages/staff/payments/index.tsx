import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { PaymentStatusBadge } from '@/components/payment-status-badge';
import { PlateChip } from '@/components/plate-chip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show } from '@/routes/staff/payments';
import type { BreadcrumbItem, Paginated, Payment } from '@/types';
import { PAYMENT_STATUSES } from '@/types/enums';
import type { PaymentStatus } from '@/types/enums';

type Props = {
    payments: Paginated<Payment>;
    filters: {
        /** "submitted" is the default queue; "all" lifts the filter. */
        status: PaymentStatus | 'all';
        receipt: string;
    };
};

export default function StaffPaymentsIndex({ payments, filters }: Props) {
    const { t } = useTranslation();
    const [status, setStatus] = useState<string>(filters.status);
    const [receipt, setReceipt] = useState(filters.receipt);

    const filtered = filters.status !== 'submitted' || filters.receipt !== '';

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            index.url(),
            {
                ...(status !== 'submitted' && { status }),
                ...(receipt.trim() !== '' && { receipt: receipt.trim() }),
            },
            { preserveState: true, replace: true },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('payments.cashier.queue_title'), href: index() },
    ];

    const labelClass = 'text-eyebrow text-muted-foreground';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('payments.cashier.queue_title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('payments.cashier.queue_title')}
                    description={t('payments.cashier.queue_intro')}
                />

                <form
                    role="search"
                    onSubmit={search}
                    className="grid gap-3 rounded-2xl border border-border bg-card p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] sm:items-end"
                >
                    <div className="grid gap-1.5">
                        <label htmlFor="filter-status" className={labelClass}>
                            {t('payments.cashier.filters.status')}
                        </label>
                        <NativeSelect
                            id="filter-status"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="submitted">
                                {t('payments.cashier.filters.waiting')}
                            </option>
                            <option value="all">
                                {t('payments.cashier.filters.all')}
                            </option>
                            {PAYMENT_STATUSES.filter(
                                (value) => value !== 'submitted',
                            ).map((value) => (
                                <option key={value} value={value}>
                                    {t(`payments.status.${value}`)}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-1.5">
                        <label htmlFor="filter-receipt" className={labelClass}>
                            {t('payments.cashier.filters.receipt')}
                        </label>
                        <Input
                            id="filter-receipt"
                            type="search"
                            value={receipt}
                            maxLength={64}
                            translate="no"
                            onChange={(event) => setReceipt(event.target.value)}
                        />
                    </div>
                    <Button type="submit" className="h-11">
                        <Search aria-hidden="true" />
                        {t('payments.cashier.filters.apply')}
                    </Button>
                    {filtered && (
                        <Button asChild variant="outline" className="h-11">
                            <Link href={index()}>
                                {t('payments.cashier.filters.clear')}
                            </Link>
                        </Button>
                    )}
                </form>

                <p
                    className="text-sm text-muted-foreground tabular-nums"
                    aria-live="polite"
                >
                    {payments.meta.total === 0
                        ? t('payments.cashier.empty')
                        : t('payments.cashier.count', {
                              count: payments.meta.total,
                          })}
                </p>

                <ul className="grid gap-3">
                    {payments.data.map((payment) => (
                        <li
                            key={payment.id}
                            className="grid gap-4 rounded-2xl border border-border bg-card p-4 sm:p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                        >
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                    <PaymentStatusBadge
                                        status={payment.status}
                                    />
                                    <span
                                        translate="no"
                                        className="font-extrabold tracking-wide tabular-nums"
                                    >
                                        {payment.receipt_number}
                                    </span>
                                    <span className="font-bold tabular-nums">
                                        {payment.amount.formatted}
                                    </span>
                                    {payment.permit?.organization && (
                                        <span className="text-muted-foreground">
                                            {payment.permit.organization}
                                        </span>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {payment.permit?.permit_number && (
                                        <span
                                            translate="no"
                                            className="font-bold tabular-nums"
                                        >
                                            {payment.permit.permit_number}
                                        </span>
                                    )}
                                    {(payment.permit?.vehicles ?? []).map(
                                        (vehicle) => (
                                            <PlateChip
                                                key={vehicle.id}
                                                plate={vehicle.plate}
                                            />
                                        ),
                                    )}
                                </div>
                                {payment.submitted_at && (
                                    <p className="flex flex-wrap items-baseline gap-x-2 text-sm">
                                        <span className={labelClass}>
                                            {t('payments.fields.submitted')}
                                        </span>
                                        <DualDate date={payment.submitted_at} />
                                    </p>
                                )}
                            </div>
                            <Button asChild variant="outline">
                                <Link href={show({ payment: payment.id })}>
                                    {t('permits.actions.view')}
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>

                <Pagination links={payments.links} meta={payments.meta} />
            </div>
        </AppLayout>
    );
}
