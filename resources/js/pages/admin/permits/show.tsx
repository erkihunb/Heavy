import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { PaymentList } from '@/components/payment-list';
import { PermitDetails } from '@/components/permit-details';
import { PermitDocumentLinks } from '@/components/permit-document-links';
import { PermitLetter } from '@/components/permit-letter';
import { PermitTimeline } from '@/components/permit-timeline';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { destroy, edit, index, show, submit } from '@/routes/admin/permits';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import { useConfirm } from '@/shell/hooks/useConfirm';
import type { BreadcrumbItem, Permit } from '@/types';
import type { WalkInApplicant } from '@/types/admin';

type Props = {
    applicant: WalkInApplicant;
    permit: Permit;
    /** What the Registrar may do now (App\Policies\PermitPolicy::*For). */
    actions: { update: boolean; submit: boolean; delete: boolean };
};

/**
 * A request taken at the office, as the Registrar opens it from Permits:
 * correct and submit it while it is theirs to (a draft, or sent back for
 * changes) and print the permit once it is issued. The fee is taken by the
 * cashier, on the letter's page.
 */
export default function PermitShow(props: Props) {
    return (
        <ConfirmProvider>
            <PermitShowPage {...props} />
        </ConfirmProvider>
    );
}

function PermitShowPage({ applicant, permit, actions }: Props) {
    const { t } = useTranslation();
    const { confirm } = useConfirm();
    const [busy, setBusy] = useState(false);
    const route = permit.id;
    const payments = permit.payments ?? [];

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

    // Deleting keeps the request in the record as withdrawn.
    const remove = async () => {
        const { confirmed } = await confirm({
            title: t('permits.delete.title'),
            description: t('permits.delete.body'),
            confirmLabel: t('permits.delete.confirm'),
            variant: 'danger',
        });

        if (confirmed) {
            router.delete(destroy.url(route), {
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            });
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('register.title'), href: index() },
        {
            title: permit.permit_number ?? t('permits.show_title'),
            href: show(route),
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

                {(actions.submit || actions.update || actions.delete) && (
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
                            {actions.delete && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="lg"
                                    disabled={busy}
                                    onClick={remove}
                                    className="sm:ml-auto"
                                >
                                    <Trash2 aria-hidden="true" />
                                    {t('permits.delete.button')}
                                </Button>
                            )}
                        </div>
                    </section>
                )}

                {permit.document_urls && (
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
                        <PermitDocumentLinks
                            urls={permit.document_urls}
                            size="lg"
                        />
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

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
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

                        {permit.letter && (
                            <div className="mt-6">
                                <PermitLetter letter={permit.letter} />
                            </div>
                        )}

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
                        <Link href={index()}>
                            <ArrowLeft aria-hidden="true" />
                            {t('applicants.back')}
                        </Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
