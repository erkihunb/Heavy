import { DualDate } from '@/components/dual-date';
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
import type {
    DualDate as DualDateValue,
    Permit,
    VehicleHistory,
} from '@/types';

type Props = {
    permit: Permit;
    vehicleHistory: VehicleHistory[];
    /** Every QR scan and number-and-plate lookup of this permit. */
    scans: {
        total: number;
        qr: number;
        lookup: number;
        by_officers: number;
        by_public: number;
        last: { date: DualDateValue; time: string } | null;
    };
    desks: Desk[];
};

function ScansCard({ scans }: { scans: Props['scans'] }) {
    const { t } = useTranslation();

    const rows: [string, number][] = [
        [t('verification.officer.channels.qr'), scans.qr],
        [t('verification.officer.channels.lookup'), scans.lookup],
        [t('verification.officer.checks.by_officers'), scans.by_officers],
        [t('verification.officer.scan_by_public'), scans.by_public],
    ];

    return (
        <section
            aria-labelledby="checks-title"
            className="rounded-card border border-border bg-card p-5"
        >
            <h2 id="checks-title" className="text-section-title">
                {t('verification.officer.checks.title')}
            </h2>

            {scans.total === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    {t('verification.officer.checks.never')}
                </p>
            ) : (
                <>
                    <p className="mt-3 text-eyebrow text-muted-foreground">
                        {t('verification.officer.checks.total')}
                    </p>
                    <p className="text-2xl font-bold tabular-nums">
                        {scans.total}
                    </p>
                    <dl className="mt-3 space-y-1 text-sm">
                        {rows.map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-4"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-semibold tabular-nums">
                                    {value}
                                </dd>
                            </div>
                        ))}
                        {scans.last && (
                            <div className="flex flex-wrap justify-between gap-x-4 border-t border-border pt-2">
                                <dt className="text-muted-foreground">
                                    {t('verification.officer.checks.last')}
                                </dt>
                                <dd className="inline-flex flex-wrap items-baseline gap-x-2">
                                    <DualDate date={scans.last.date} />
                                    <span className="tabular-nums">
                                        {scans.last.time}
                                    </span>
                                </dd>
                            </div>
                        )}
                    </dl>
                </>
            )}
        </section>
    );
}

/**
 * An application, read only, for every member of staff who sees it: the
 * registered details, the vehicles' permits, the head office's comments and
 * the timeline. Nobody acts here: each role opens its own page (review and
 * decision, payment, issue and print) from "Your work on this application".
 */
export default function StaffPermitsShow({
    permit,
    vehicleHistory,
    scans,
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
                    {permit.permit_number && <ScansCard scans={scans} />}

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
