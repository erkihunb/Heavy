import { Table, TBody, Td, Th, THead, Tr } from '@/components/data-table';
import { DualDate } from '@/components/dual-date';
import { PaymentStatusBadge } from '@/components/payment-status-badge';
import { useTranslation } from '@/lib/i18n';
import type { Payment } from '@/types';

/**
 * The receipts submitted for one permit, newest first, as a table with a
 * link to each receipt file. `receiptUrl` decides which authorised route
 * opens the file. A cashier's note runs under its payment.
 */
export function PaymentList({
    payments,
    receiptUrl,
}: {
    payments: Payment[];
    receiptUrl: (payment: Payment) => string;
}) {
    const { t } = useTranslation();

    return (
        <div className="overflow-hidden rounded-card border border-border bg-card">
            <Table>
                <THead>
                    <tr>
                        <Th>{t('payments.fields.status')}</Th>
                        <Th>{t('payments.fields.receipt_number')}</Th>
                        <Th align="right">{t('payments.fields.amount')}</Th>
                        <Th>{t('payments.fields.method')}</Th>
                        <Th>{t('payments.fields.paid_at')}</Th>
                        <Th align="right">
                            <span className="sr-only">
                                {t('common.actions')}
                            </span>
                        </Th>
                    </tr>
                </THead>
                <TBody>
                    {[...payments].reverse().map((payment) => [
                        <Tr key={payment.id}>
                            <Td>
                                <PaymentStatusBadge status={payment.status} />
                            </Td>
                            <Td
                                translate="no"
                                className="font-semibold whitespace-nowrap tabular-nums"
                            >
                                {payment.receipt_number}
                            </Td>
                            <Td numeric className="whitespace-nowrap">
                                {payment.amount.formatted}
                            </Td>
                            <Td muted>
                                {t(`payments.methods.${payment.method}`)}
                            </Td>
                            <Td className="whitespace-nowrap">
                                <DualDate date={payment.paid_at} />
                            </Td>
                            <Td align="right">
                                {payment.has_receipt_file && (
                                    <a
                                        href={receiptUrl(payment)}
                                        target="_blank"
                                        rel="noopener"
                                        className="font-medium whitespace-nowrap text-primary underline-offset-4 hover:underline"
                                    >
                                        {t('payments.view_receipt')}
                                    </a>
                                )}
                            </Td>
                        </Tr>,
                        payment.notes ? (
                            <tr key={`${payment.id}-note`}>
                                <td colSpan={6} className="px-4 pt-0 pb-3">
                                    <p className="rounded-md bg-muted px-3 py-2 text-sm whitespace-pre-line">
                                        <span className="font-medium">
                                            {t('payments.fields.note')}:{' '}
                                        </span>
                                        {payment.notes}
                                    </p>
                                </td>
                            </tr>
                        ) : null,
                    ])}
                </TBody>
            </Table>
        </div>
    );
}
