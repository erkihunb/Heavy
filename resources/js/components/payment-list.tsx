import { DualDate } from '@/components/dual-date';
import { PaymentStatusBadge } from '@/components/payment-status-badge';
import { useTranslation } from '@/lib/i18n';
import type { Payment } from '@/types';

/**
 * The receipts submitted for one permit, newest first, with a link to each
 * receipt file. `receiptUrl` decides which authorised route opens the file.
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
        <ul className="divide-y divide-border rounded-xl border border-border">
            {[...payments].reverse().map((payment) => (
                <li key={payment.id} className="space-y-2 px-4 py-3 text-sm">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <PaymentStatusBadge status={payment.status} />
                        <span
                            translate="no"
                            className="font-bold tracking-wide tabular-nums"
                        >
                            {payment.receipt_number}
                        </span>
                        <span className="font-medium tabular-nums">
                            {payment.amount.formatted}
                        </span>
                        <span className="text-muted-foreground">
                            {t(`payments.methods.${payment.method}`)}
                        </span>
                    </div>
                    <p className="flex flex-wrap items-baseline gap-x-2 text-muted-foreground">
                        <span>{t('payments.fields.paid_at')}</span>
                        <DualDate date={payment.paid_at} />
                    </p>
                    {payment.notes && (
                        <p className="rounded-lg bg-muted px-3 py-2 whitespace-pre-line">
                            {payment.notes}
                        </p>
                    )}
                    {payment.has_receipt_file && (
                        <a
                            href={receiptUrl(payment)}
                            target="_blank"
                            rel="noopener"
                            className="inline-block font-semibold underline underline-offset-4"
                        >
                            {t('payments.view_receipt')}
                        </a>
                    )}
                </li>
            ))}
        </ul>
    );
}
