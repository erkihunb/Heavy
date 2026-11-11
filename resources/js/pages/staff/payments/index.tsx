import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('payments.cashier.queue_title')}
                    description={t('payments.cashier.queue_intro')}
                />

                <form
                    role="search"
                    onSubmit={search}
                    className="grid gap-3 rounded-card border border-border bg-card p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] sm:items-end"
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

                <TableCard>
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('payments.fields.status')}</Th>
                                <Th>{t('payments.fields.receipt_number')}</Th>
                                <Th align="right">
                                    {t('payments.fields.amount')}
                                </Th>
                                <Th>{t('permits.filters.organization')}</Th>
                                <Th>{t('permits.fields.permit_number')}</Th>
                                <Th>{t('permits.fields.vehicles')}</Th>
                                <Th>{t('payments.fields.submitted')}</Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {payments.data.length === 0 ? (
                                <EmptyRow colSpan={8}>
                                    {t('payments.cashier.empty')}
                                </EmptyRow>
                            ) : (
                                payments.data.map((payment) => (
                                    <Tr key={payment.id}>
                                        <Td>
                                            <PaymentStatusBadge
                                                status={payment.status}
                                            />
                                        </Td>
                                        <Td
                                            translate="no"
                                            className="font-semibold whitespace-nowrap tabular-nums"
                                        >
                                            {payment.receipt_number}
                                        </Td>
                                        <Td
                                            numeric
                                            className="font-semibold whitespace-nowrap"
                                        >
                                            {payment.amount.formatted}
                                        </Td>
                                        <Td>
                                            {payment.permit?.organization ??
                                                '—'}
                                        </Td>
                                        <Td
                                            translate="no"
                                            className="whitespace-nowrap tabular-nums"
                                        >
                                            {payment.permit?.permit_number ??
                                                '—'}
                                        </Td>
                                        <Td>
                                            <span className="flex flex-wrap gap-1.5">
                                                {(
                                                    payment.permit?.vehicles ??
                                                    []
                                                ).map((vehicle) => (
                                                    <PlateChip
                                                        key={vehicle.id}
                                                        plate={vehicle.plate}
                                                    />
                                                ))}
                                            </span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {payment.submitted_at ? (
                                                <DualDate
                                                    date={payment.submitted_at}
                                                />
                                            ) : (
                                                '—'
                                            )}
                                        </Td>
                                        <Td align="right">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={show({
                                                        payment: payment.id,
                                                    })}
                                                >
                                                    {t('permits.actions.view')}
                                                </Link>
                                            </Button>
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>

                <Pagination links={payments.links} meta={payments.meta} />
            </div>
        </AppLayout>
    );
}
