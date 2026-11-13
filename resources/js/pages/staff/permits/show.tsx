import { PermitComments } from '@/components/permit-comments';
import { PermitDocumentLinks } from '@/components/permit-document-links';
import { PermitLetter } from '@/components/permit-letter';
import { PermitTimeline } from '@/components/permit-timeline';
import {
    DeskLinks,
    PermitDetailsCard,
    RecommendationCard,
    StaffPermitFrame,
    VehicleHistoryCard,
} from '@/components/staff-permit-frame';
import type { Desk } from '@/components/staff-permit-frame';
import { useTranslation } from '@/lib/i18n';
import { store as storeComment } from '@/routes/staff/permits/comments';
import type { Permit, VehicleHistory } from '@/types';

type Props = {
    permit: Permit;
    vehicleHistory: VehicleHistory[];
    desks: Desk[];
};

/**
 * An application, read only, for every member of staff who sees it: the
 * registered details, the vehicles' permits, the head office's comments and
 * the timeline. Nobody acts here: each role opens its own page (review and
 * decision, payment, issue and print) from "Your work on this application".
 */
export default function StaffPermitsShow({
    permit,
    vehicleHistory,
    desks,
}: Props) {
    const { t } = useTranslation();
    const { can } = permit;

    return (
        <StaffPermitFrame
            permit={permit}
            vehicleHistory={vehicleHistory}
            title={t('permits.staff.detail_title')}
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div className="space-y-6">
                    <PermitDetailsCard permit={permit} />
                    <VehicleHistoryCard vehicleHistory={vehicleHistory} />

                    {(permit.comments !== undefined || can.comment) && (
                        <PermitComments
                            comments={permit.comments ?? []}
                            action={
                                can.comment
                                    ? storeComment.url({ permit: permit.id })
                                    : null
                            }
                        />
                    )}

                    <section
                        aria-labelledby="timeline-title"
                        className="rounded-card border border-border bg-card p-5 sm:p-6"
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

                <div className="h-fit space-y-4 lg:sticky lg:top-6">
                    <DeskLinks desks={desks} />
                    <RecommendationCard permit={permit} />
                    {permit.letter && <PermitLetter letter={permit.letter} />}

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
