import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { PaymentForm } from '@/components/payment-form';
import { PaymentList } from '@/components/payment-list';
import { PermitDetails } from '@/components/permit-details';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show } from '@/routes/admin/applicants';
import { payment, show as showPermit } from '@/routes/admin/applicants/permits';
import { store as storePayment } from '@/routes/admin/applicants/permits/payments';
import type { BreadcrumbItem, Permit } from '@/types';
import type { WalkInApplicant } from '@/types/admin';
import type { PaymentMethod } from '@/types/enums';

type Props = {
    applicant: WalkInApplicant;
    permit: Permit;
    paymentMethods: PaymentMethod[];
    receiptMaxKilobytes: number;
};

/**
 * The Registrar's payment page for an approved application: the registered
 * application read only, the fee fixed at approval, and the receipt the
 * applicant brought. Nothing of the application itself changes here; the
 * cashier verifies the receipt before the permit is issued.
 */
export default function WalkInPermitPayment({
    applicant,
    permit,
    paymentMethods,
    receiptMaxKilobytes,
}: Props) {
    const { t } = useTranslation();
    const route = { applicant: applicant.id, permit: permit.id };
    const payments = permit.payments ?? [];
    const lastRejected = [...payments]
        .reverse()
        .find((item) => item.status === 'rejected');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
        { title: applicant.organization, href: show(applicant.id) },
        {
            title: permit.permit_number ?? t('permits.show_title'),
            href: showPermit(route),
        },
        { title: t('payments.enter'), href: payment(route) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('payments.enter')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('payments.enter')}
                    description={t('applicants.for', {
                        organization: applicant.organization,
                    })}
                    action={<StatusBadge status={permit.effective_status} />}
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                    <section
                        aria-labelledby="details-title"
                        className="h-fit rounded-card border border-border bg-card p-5 sm:p-6"
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

                    {permit.fee && (
                        <section
                            aria-labelledby="payment-title"
                            className="h-fit space-y-5 rounded-card border-2 border-primary bg-card p-6 sm:p-7"
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
                                fee={permit.fee}
                                methods={paymentMethods}
                                maxKilobytes={receiptMaxKilobytes}
                                action={storePayment.url(route)}
                            />
                        </section>
                    )}
                </div>

                <div>
                    <Button asChild variant="outline">
                        <Link href={showPermit(route)}>
                            <ArrowLeft aria-hidden="true" />
                            {t('permits.staff.desks.back')}
                        </Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
