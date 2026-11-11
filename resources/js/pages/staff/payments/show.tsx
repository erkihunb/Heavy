import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CircleAlert, CircleCheck, FileText } from 'lucide-react';
import type { ReactNode } from 'react';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { PaymentList } from '@/components/payment-list';
import { PaymentStatusBadge } from '@/components/payment-status-badge';
import { PermitDetails } from '@/components/permit-details';
import { StaffAction } from '@/components/staff-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index, receipt, reject, show, verify } from '@/routes/staff/payments';
import type { BreadcrumbItem, Payment } from '@/types';

type Props = {
    payment: Payment;
    /** The viewer approved this permit and so may not verify its payment. */
    approvedByViewer: boolean;
};

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4">
            <dt className="text-eyebrow text-muted-foreground">{label}</dt>
            <dd className="min-w-0">{children}</dd>
        </div>
    );
}

export default function StaffPaymentsShow({
    payment,
    approvedByViewer,
}: Props) {
    const { t } = useTranslation();
    const permit = payment.permit;
    const args = { payment: payment.id };
    const fee = permit?.fee ?? null;
    const matches =
        fee !== null && fee.amount_cents === payment.amount.amount_cents;
    const earlier = (permit?.payments ?? []).filter(
        (other) => other.id !== payment.id,
    );
    const hasAction = payment.can.verify || payment.can.reject;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('payments.cashier.queue_title'), href: index() },
        { title: payment.receipt_number, href: show(args) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('payments.cashier.detail_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('payments.cashier.detail_title')}
                    description={permit?.organization}
                    action={<PaymentStatusBadge status={payment.status} />}
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <div className="space-y-6">
                        <section
                            aria-labelledby="payment-title"
                            className="rounded-card border border-border bg-card p-5 sm:p-6"
                        >
                            <h2
                                id="payment-title"
                                className="mb-2 text-section-title"
                            >
                                {t('payments.title')}
                            </h2>
                            <dl className="divide-y divide-border">
                                <Row
                                    label={t('payments.fields.receipt_number')}
                                >
                                    <span
                                        translate="no"
                                        className="text-lg font-extrabold tracking-wide tabular-nums"
                                    >
                                        {payment.receipt_number}
                                    </span>
                                </Row>
                                <Row label={t('payments.fields.amount')}>
                                    <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span className="text-lg font-extrabold tabular-nums">
                                            {payment.amount.formatted}
                                        </span>
                                        {fee && (
                                            <span
                                                className={
                                                    matches
                                                        ? 'inline-flex items-center gap-1 text-sm font-bold text-success'
                                                        : 'inline-flex items-center gap-1 text-sm font-bold text-destructive'
                                                }
                                            >
                                                {matches ? (
                                                    <CircleCheck
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                ) : (
                                                    <CircleAlert
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                )}
                                                {matches
                                                    ? t(
                                                          'payments.cashier.matches',
                                                      )
                                                    : t(
                                                          'payments.cashier.differs',
                                                      )}
                                            </span>
                                        )}
                                    </span>
                                </Row>
                                {fee && (
                                    <Row label={t('payments.cashier.expected')}>
                                        <span className="font-bold tabular-nums">
                                            {fee.formatted}
                                        </span>
                                    </Row>
                                )}
                                <Row label={t('payments.fields.method')}>
                                    {t(`payments.methods.${payment.method}`)}
                                </Row>
                                <Row label={t('payments.fields.paid_at')}>
                                    <DualDate date={payment.paid_at} />
                                </Row>
                                {payment.submitted_at && (
                                    <Row label={t('payments.fields.submitted')}>
                                        <span className="flex flex-wrap items-baseline gap-x-2">
                                            <DualDate
                                                date={payment.submitted_at}
                                            />
                                            {payment.submitted_by && (
                                                <span className="text-sm text-muted-foreground">
                                                    {t('permits.timeline.by', {
                                                        name: payment.submitted_by,
                                                    })}
                                                </span>
                                            )}
                                        </span>
                                    </Row>
                                )}
                                {payment.decided_at && (
                                    <Row label={t('payments.fields.decided')}>
                                        <span className="flex flex-wrap items-baseline gap-x-2">
                                            <DualDate
                                                date={payment.decided_at}
                                            />
                                            {payment.decided_by && (
                                                <span className="text-sm text-muted-foreground">
                                                    {t('permits.timeline.by', {
                                                        name: payment.decided_by,
                                                    })}
                                                </span>
                                            )}
                                        </span>
                                    </Row>
                                )}
                                {payment.notes && (
                                    <Row label={t('payments.fields.note')}>
                                        <p className="whitespace-pre-line">
                                            {payment.notes}
                                        </p>
                                    </Row>
                                )}
                            </dl>
                            {payment.has_receipt_file && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-4"
                                >
                                    <a
                                        href={receipt.url(args)}
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <FileText aria-hidden="true" />
                                        {t('payments.view_receipt')}
                                    </a>
                                </Button>
                            )}
                        </section>

                        {permit && (
                            <section
                                aria-labelledby="permit-title"
                                className="rounded-card border border-border bg-card p-5 sm:p-6"
                            >
                                <h2
                                    id="permit-title"
                                    className="mb-2 text-section-title"
                                >
                                    {t('payments.fields.permit')}
                                </h2>
                                <PermitDetails permit={permit} />
                            </section>
                        )}

                        {earlier.length > 0 && (
                            <section
                                aria-labelledby="earlier-title"
                                className="space-y-3 rounded-card border border-border bg-card p-5 sm:p-6"
                            >
                                <h2
                                    id="earlier-title"
                                    className="text-section-title"
                                >
                                    {t('payments.cashier.other_payments')}
                                </h2>
                                <PaymentList
                                    payments={earlier}
                                    receiptUrl={(other) =>
                                        receipt.url({ payment: other.id })
                                    }
                                />
                            </section>
                        )}
                    </div>

                    <section
                        aria-labelledby="decision-title"
                        className="h-fit space-y-4 lg:sticky lg:top-6"
                    >
                        <h2 id="decision-title" className="text-section-title">
                            {t('permits.staff.decision')}
                        </h2>

                        {!hasAction && (
                            <p className="rounded-card border border-border bg-card p-5 text-sm text-muted-foreground">
                                {t('payments.cashier.no_action')}
                            </p>
                        )}

                        {hasAction && approvedByViewer && (
                            <p className="rounded-card border border-border bg-card p-5 text-sm font-medium">
                                {t('payments.cashier.own_approval')}
                            </p>
                        )}

                        {payment.can.verify && !approvedByViewer && (
                            <StaffAction
                                id="verify"
                                title={t('payments.cashier.verify.title')}
                                hint={t('payments.cashier.verify.hint')}
                                action={verify.url(args)}
                                submitLabel={t(
                                    'payments.cashier.verify.submit',
                                )}
                                note="optional"
                            />
                        )}

                        {payment.can.reject && (
                            <StaffAction
                                id="reject"
                                title={t('payments.cashier.reject.title')}
                                hint={t('payments.cashier.reject.hint')}
                                action={reject.url(args)}
                                submitLabel={t(
                                    'payments.cashier.reject.submit',
                                )}
                                note="required"
                                noteLabel={t(
                                    'permits.staff.note_for_applicant',
                                )}
                                destructive
                            />
                        )}
                    </section>
                </div>

                <Button asChild variant="outline" className="w-fit">
                    <Link href={index()}>
                        <ArrowLeft aria-hidden="true" />
                        {t('permits.actions.back')}
                    </Link>
                </Button>
            </div>
        </AppLayout>
    );
}
