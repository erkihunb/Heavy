import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileDown, Pencil, Send } from 'lucide-react';
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
import { index, show } from '@/routes/admin/applicants';
import {
    edit,
    show as showPermit,
    submit,
} from '@/routes/admin/applicants/permits';
import { store as storePayment } from '@/routes/admin/applicants/permits/payments';
import type { BreadcrumbItem, Permit } from '@/types';
import type { WalkInApplicant } from '@/types/admin';
import type { PaymentMethod } from '@/types/enums';

type Props = {
    applicant: WalkInApplicant;
    permit: Permit;
    /** What the Registrar may do now (App\Policies\PermitPolicy::*For). */
    actions: { update: boolean; submit: boolean; submitPayment: boolean };
    paymentMethods: PaymentMethod[];
    receiptMaxKilobytes: number;
};

/**
 * A walk-in applicant's application, as the Registrar at the office sees
 * it: submit it for review, hand in the payment receipt once it is
 * approved, and print the permit once it is issued.
 */
export default function WalkInPermitShow({
    applicant,
    permit,
    actions,
    paymentMethods,
    receiptMaxKilobytes,
}: Props) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const route = { applicant: applicant.id, permit: permit.id };
    const payments = permit.payments ?? [];
    const pendingPayment = payments.some(
        (payment) => payment.status === 'submitted',
    );
    const lastRejected = [...payments]
        .reverse()
        .find((payment) => payment.status === 'rejected');

    const send = () =>
        router.post(
            submit.url(route),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
        { title: applicant.organization, href: show(applicant.id) },
        {
            title: permit.permit_number ?? t('permits.show_title'),
            href: showPermit(route),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.show_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('permits.show_title')}
                    description={t('applicants.for', {
                        organization: applicant.organization,
                    })}
                    action={<StatusBadge status={permit.effective_status} />}
                />

                {(actions.submit || actions.update) && (
                    <section
                        aria-labelledby="review-title"
                        className="space-y-4 rounded-card border-2 border-primary bg-card p-6 sm:p-7"
                    >
                        <div className="space-y-1.5">
                            <h2
                                id="review-title"
                                className="text-section-title"
                            >
                                {t('permits.review.title')}
                            </h2>
                            <p className="max-w-2xl text-muted-foreground">
                                {permit.status === 'changes_requested'
                                    ? t('permits.review.changes_requested')
                                    : t('applicants.review_draft')}
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            {actions.submit && (
                                <Button
                                    type="button"
                                    size="lg"
                                    disabled={busy}
                                    onClick={send}
                                >
                                    {busy ? (
                                        <Spinner />
                                    ) : (
                                        <Send aria-hidden="true" />
                                    )}
                                    {permit.status === 'changes_requested'
                                        ? t('permits.actions.resubmit')
                                        : t('permits.actions.submit')}
                                </Button>
                            )}
                            {actions.update && (
                                <Button asChild variant="outline" size="lg">
                                    <Link href={edit(route)}>
                                        <Pencil aria-hidden="true" />
                                        {t('permits.actions.edit')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </section>
                )}

                {permit.document_url && (
                    <section
                        aria-labelledby="document-title"
                        className="flex flex-col gap-4 rounded-card border-2 border-success bg-card p-6 sm:flex-row sm:items-center sm:justify-between sm:p-7"
                    >
                        <div className="space-y-1.5">
                            <h2
                                id="document-title"
                                className="text-section-title"
                            >
                                {t('applicants.print_title')}
                            </h2>
                            <p className="max-w-xl text-muted-foreground">
                                {t('applicants.print_intro')}
                            </p>
                        </div>
                        <Button asChild size="lg" className="shrink-0">
                            <a
                                href={permit.document_url}
                                target="_blank"
                                rel="noopener"
                            >
                                <FileDown aria-hidden="true" />
                                {t('permits.actions.download')}
                            </a>
                        </Button>
                    </section>
                )}

                {permit.document_pending && (
                    <p
                        role="status"
                        className="rounded-card border border-border bg-muted px-5 py-4 font-medium"
                    >
                        {t('permits.document.pending')}
                    </p>
                )}

                {pendingPayment && (
                    <p
                        role="status"
                        className="rounded-card border border-border bg-muted px-5 py-4 font-medium"
                    >
                        {t('payments.waiting_notice')}
                    </p>
                )}

                {actions.submitPayment && permit.fee && (
                    <section
                        aria-labelledby="payment-title"
                        className="space-y-5 rounded-card border-2 border-primary bg-card p-6 sm:p-7"
                    >
                        <div className="space-y-1.5">
                            <h2
                                id="payment-title"
                                className="text-section-title"
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
                                className="rounded-card border-2 border-destructive px-4 py-3 font-medium"
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
                            action={storePayment.url(route)}
                        />
                    </section>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <section
                        aria-labelledby="details-title"
                        className="rounded-card border border-border bg-card p-5 sm:p-6"
                    >
                        <h2
                            id="details-title"
                            className="mb-2 text-section-title"
                        >
                            {t('permits.review.details')}
                        </h2>
                        <PermitDetails permit={permit} />

                        {payments.length > 0 && (
                            <div className="mt-6 space-y-3">
                                <h3 className="text-section-title">
                                    {t('payments.history')}
                                </h3>
                                <PaymentList payments={payments} />
                            </div>
                        )}
                    </section>

                    <section
                        aria-labelledby="timeline-title"
                        className="h-fit rounded-card border border-border bg-card p-5 sm:p-6"
                    >
                        <h2
                            id="timeline-title"
                            className="mb-6 text-section-title"
                        >
                            {t('permits.timeline.title')}
                        </h2>
                        <PermitTimeline
                            transitions={permit.transitions ?? []}
                        />
                    </section>
                </div>

                <div>
                    <Button asChild variant="outline">
                        <Link href={show(applicant.id)}>
                            <ArrowLeft aria-hidden="true" />
                            {t('applicants.back')}
                        </Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
