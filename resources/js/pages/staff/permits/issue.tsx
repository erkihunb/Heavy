import { PermitDocumentLinks } from '@/components/permit-document-links';
import { StaffAction } from '@/components/staff-action';
import {
    PermitDetailsCard,
    StaffPermitFrame,
} from '@/components/staff-permit-frame';
import { useTranslation } from '@/lib/i18n';
import { issue } from '@/routes/staff/permits';
import type { Permit, VehicleHistory } from '@/types';

type Props = {
    permit: Permit;
    vehicleHistory: VehicleHistory[];
};

/**
 * The Issuer's page: the application read only and the verified payment,
 * then issuing the permit (it gets its number and its documents) and
 * printing it — only once the payment is confirmed.
 */
export default function StaffPermitIssue({ permit, vehicleHistory }: Props) {
    const { t } = useTranslation();
    const { can } = permit;
    const verifiedPayment = permit.payments?.find(
        (payment) => payment.status === 'verified',
    );

    return (
        <StaffPermitFrame
            permit={permit}
            vehicleHistory={vehicleHistory}
            title={t('permits.staff.desks.issue_title')}
            description={t('permits.staff.desks.issue_intro')}
            rolePage
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <PermitDetailsCard permit={permit} />

                <div className="h-fit space-y-4 lg:sticky lg:top-6">
                    {verifiedPayment && (
                        <p className="flex items-baseline justify-between gap-3 rounded-card border border-border bg-card px-5 py-4">
                            <span className="text-sm text-muted-foreground">
                                {t('permits.staff.issue.paid')} ·{' '}
                                <span translate="no">
                                    {verifiedPayment.receipt_number}
                                </span>
                            </span>
                            <span className="font-semibold tabular-nums">
                                {verifiedPayment.amount.formatted}
                            </span>
                        </p>
                    )}

                    {can.issue ? (
                        <StaffAction
                            id="issue"
                            title={t('permits.staff.issue.title')}
                            hint={t('permits.staff.issue.hint')}
                            action={issue.url({ permit: permit.id })}
                            submitLabel={t('permits.staff.issue.submit')}
                            note="optional"
                        />
                    ) : (
                        !permit.document_urls && (
                            <p className="rounded-card border border-border bg-card p-5 text-sm text-muted-foreground">
                                {t('permits.staff.desks.no_issue')}
                            </p>
                        )
                    )}

                    {permit.issued_by && (
                        <p className="text-sm text-muted-foreground">
                            {t('permits.staff.issued_by', {
                                name: permit.issued_by,
                            })}
                        </p>
                    )}

                    {permit.document_pending && (
                        <p
                            role="status"
                            className="rounded-card border border-border bg-card p-5 text-sm text-muted-foreground"
                        >
                            {t('permits.document.pending')}
                        </p>
                    )}

                    {permit.document_urls && (
                        <PermitDocumentLinks
                            urls={permit.document_urls}
                            variant="outline"
                            stacked
                        />
                    )}
                </div>
            </div>
        </StaffPermitFrame>
    );
}
