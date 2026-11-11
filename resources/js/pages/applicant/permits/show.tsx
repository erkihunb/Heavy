import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Send, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { PaymentForm } from '@/components/payment-form';
import { PaymentList } from '@/components/payment-list';
import { PermitDetails } from '@/components/permit-details';
import { PermitTimeline } from '@/components/permit-timeline';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { receipt } from '@/routes/payments';
import { edit, index, show, submit, withdraw } from '@/routes/permits';
import type { BreadcrumbItem, Permit } from '@/types';
import type { PaymentMethod } from '@/types/enums';

type Props = {
    permit: Permit;
    /** The payment methods an applicant can choose. */
    paymentMethods: PaymentMethod[];
    receiptMaxKilobytes: number;
};

export default function PermitsShow({
    permit,
    paymentMethods,
    receiptMaxKilobytes,
}: Props) {
    const { t } = useTranslation();
    const payments = permit.payments ?? [];
    const pendingPayment = payments.some(
        (payment) => payment.status === 'submitted',
    );
    const lastRejected = [...payments]
        .reverse()
        .find((payment) => payment.status === 'rejected');
    const [busy, setBusy] = useState<'submit' | 'withdraw' | null>(null);

    const post = (action: 'submit' | 'withdraw') => {
        const url =
            action === 'submit'
                ? submit.url({ permit: permit.id })
                : withdraw.url({ permit: permit.id });

        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(action),
                onFinish: () => setBusy(null),
            },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('permits.title'), href: index() },
        {
            title: permit.permit_number ?? t('permits.show_title'),
            href: show({ permit: permit.id }),
        },
    ];

    const canAct = permit.can.submit || permit.can.update;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.show_title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('permits.show_title')}
                    action={<StatusBadge status={permit.effective_status} />}
                />

                {canAct && (
                    <section
                        aria-labelledby="review-title"
                        className="space-y-4 rounded-2xl border-2 border-primary bg-card p-6 sm:p-7"
                    >
                        <div className="space-y-1.5">
                            <h2
                                id="review-title"
                                className="text-2xl text-display"
                            >
                                {t('permits.review.title')}
                            </h2>
                            <p className="max-w-2xl text-muted-foreground">
                                {permit.status === 'changes_requested'
                                    ? t('permits.review.changes_requested')
                                    : t('permits.review.draft')}
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            {permit.can.submit && (
                                <Button
                                    type="button"
                                    size="lg"
                                    disabled={busy !== null}
                                    onClick={() => post('submit')}
                                >
                                    {busy === 'submit' ? (
                                        <Spinner />
                                    ) : (
                                        <Send aria-hidden="true" />
                                    )}
                                    {permit.status === 'changes_requested'
                                        ? t('permits.actions.resubmit')
                                        : t('permits.actions.submit')}
                                </Button>
                            )}
                            {permit.can.update && (
                                <Button asChild variant="outline" size="lg">
                                    <Link href={edit({ permit: permit.id })}>
                                        <Pencil aria-hidden="true" />
                                        {t('permits.actions.edit')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </section>
                )}

                {pendingPayment && (
                    <p
                        role="status"
                        className="rounded-2xl border border-border bg-muted px-5 py-4 font-medium"
                    >
                        {t('payments.waiting_notice')}
                    </p>
                )}

                {permit.can.submitPayment && permit.fee && (
                    <section
                        aria-labelledby="payment-title"
                        className="space-y-5 rounded-2xl border-2 border-primary bg-card p-6 sm:p-7"
                    >
                        <div className="space-y-1.5">
                            <h2
                                id="payment-title"
                                className="text-2xl text-display"
                            >
                                {t('payments.form.title')}
                            </h2>
                            <p className="max-w-2xl text-muted-foreground">
                                {t('payments.form.intro', {
                                    fee: permit.fee.formatted,
                                })}
                            </p>
                        </div>

                        {lastRejected?.notes && (
                            <p
                                role="alert"
                                className="rounded-xl border-2 border-destructive px-4 py-3 font-medium"
                            >
                                {t('payments.rejected_notice', {
                                    note: lastRejected.notes,
                                })}
                            </p>
                        )}

                        <PaymentForm
                            permitId={permit.id}
                            fee={permit.fee}
                            methods={paymentMethods}
                            maxKilobytes={receiptMaxKilobytes}
                        />
                    </section>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <section
                        aria-labelledby="details-title"
                        className="rounded-2xl border border-border bg-card p-6 sm:p-8"
                    >
                        <h2
                            id="details-title"
                            className="mb-2 text-xl text-display"
                        >
                            {t('permits.review.details')}
                        </h2>
                        <PermitDetails permit={permit} />

                        {payments.length > 0 && (
                            <div className="mt-6 space-y-3">
                                <h3 className="text-lg font-bold">
                                    {t('payments.history')}
                                </h3>
                                <PaymentList
                                    payments={payments}
                                    receiptUrl={(payment) =>
                                        receipt.url({ payment: payment.id })
                                    }
                                />
                            </div>
                        )}
                    </section>

                    <section
                        aria-labelledby="timeline-title"
                        className="h-fit rounded-2xl border border-border bg-card p-6 sm:p-8"
                    >
                        <h2
                            id="timeline-title"
                            className="mb-6 text-xl text-display"
                        >
                            {t('permits.timeline.title')}
                        </h2>
                        <PermitTimeline
                            transitions={permit.transitions ?? []}
                        />
                    </section>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Button asChild variant="outline">
                        <Link href={index()}>
                            <ArrowLeft aria-hidden="true" />
                            {t('permits.actions.back')}
                        </Link>
                    </Button>

                    {permit.can.withdraw && (
                        <div className="flex flex-col gap-2 sm:items-end">
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={busy !== null}
                                aria-describedby="withdraw-hint"
                                onClick={() => post('withdraw')}
                            >
                                {busy === 'withdraw' ? (
                                    <Spinner />
                                ) : (
                                    <Undo2 aria-hidden="true" />
                                )}
                                {t('permits.actions.withdraw')}
                            </Button>
                            <p
                                id="withdraw-hint"
                                className="text-sm text-muted-foreground"
                            >
                                {t('permits.review.withdraw_hint')}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
