import { PaymentForm } from '@/components/payment-form';
import { PaymentList } from '@/components/payment-list';
import {
    PermitDetailsCard,
    StaffPermitFrame,
} from '@/components/staff-permit-frame';
import { useTranslation } from '@/lib/i18n';
import { recordPayment } from '@/routes/staff/permits';
import type { Permit, VehicleHistory } from '@/types';
import { PAYMENT_METHODS } from '@/types/enums';

type Props = {
    permit: Permit;
    vehicleHistory: VehicleHistory[];
    receiptMaxKilobytes: number;
};

/**
 * The cashier's page: the application read only, the fee fixed at approval,
 * the receipts handed in so far, and recording a payment taken at the office
 * or checked with the bank — only while the application awaits payment.
 * Receipts handed in by the applicant or the Registrar are verified under
 * Payments.
 */
export default function StaffPermitPayment({
    permit,
    vehicleHistory,
    receiptMaxKilobytes,
}: Props) {
    const { t } = useTranslation();
    const { can } = permit;
    const payments = permit.payments ?? [];

    return (
        <StaffPermitFrame
            permit={permit}
            vehicleHistory={vehicleHistory}
            title={t('permits.staff.desks.payment_title')}
            description={t('permits.staff.desks.payment_intro')}
            rolePage
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div className="space-y-6">
                    <PermitDetailsCard permit={permit} />
                    {payments.length > 0 && (
                        <section
                            aria-labelledby="payments-title"
                            className="space-y-3 rounded-card border border-border bg-card p-5 sm:p-6"
                        >
                            <h2
                                id="payments-title"
                                className="text-section-title"
                            >
                                {t('payments.history')}
                            </h2>
                            <PaymentList payments={payments} />
                        </section>
                    )}
                </div>

                <div className="h-fit space-y-4 lg:sticky lg:top-6">
                    {permit.fee && (
                        <p className="flex items-baseline justify-between gap-3 rounded-card border border-border bg-muted px-4 py-3">
                            <span className="text-sm text-muted-foreground">
                                {t('permits.staff.record_payment.fee')}
                            </span>
                            <span className="text-xl font-semibold tabular-nums">
                                {permit.fee.formatted}
                            </span>
                        </p>
                    )}

                    {can.recordPayment && permit.fee ? (
                        <section
                            aria-labelledby="record-payment-title"
                            className="space-y-4 rounded-card border border-border bg-card p-5"
                        >
                            <div className="space-y-1">
                                <h2
                                    id="record-payment-title"
                                    className="text-section-title"
                                >
                                    {t('permits.staff.record_payment.title')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t('permits.staff.record_payment.hint')}
                                </p>
                            </div>
                            <PaymentForm
                                fee={permit.fee}
                                methods={[...PAYMENT_METHODS]}
                                maxKilobytes={receiptMaxKilobytes}
                                action={recordPayment.url({
                                    permit: permit.id,
                                })}
                                forCashier
                            />
                        </section>
                    ) : (
                        <p className="rounded-card border border-border bg-card p-5 text-sm text-muted-foreground">
                            {t('permits.staff.desks.no_payment')}
                        </p>
                    )}
                </div>
            </div>
        </StaffPermitFrame>
    );
}
