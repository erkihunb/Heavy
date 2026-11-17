import { usePage } from '@inertiajs/react';
import { DecisionActions } from '@/components/decision-actions';
import { PermitDocumentLinks } from '@/components/permit-document-links';
import { PermitLetter } from '@/components/permit-letter';
import { ReviewForm, ReviewList } from '@/components/permit-reviews';
import { StaffAction } from '@/components/staff-action';
import {
    PermitDetailsCard,
    StaffPermitFrame,
    VehicleHistoryCard,
} from '@/components/staff-permit-frame';
import { useTranslation } from '@/lib/i18n';
import {
    approve,
    recommend,
    reissue,
    reject,
    requestChanges,
    revoke,
    startReview,
} from '@/routes/staff/permits';
import type { Permit, VehicleHistory } from '@/types';

type Props = {
    permit: Permit;
    vehicleHistory: VehicleHistory[];
    /** Opened on the application's own address, because it waits for this person. */
    direct: boolean;
};

/**
 * The Reviewer's and Approver's page: the registered details, the letter and
 * the vehicles' permits on the left, the decision on the right — start the
 * review, write a review (an assessment and a recommendation, read by the
 * Approver with every other review), approve, ask for changes or reject;
 * once issued, revoke or reissue. Only what this person may do now is shown;
 * the server checks every action again.
 */
export default function StaffPermitDecision({
    permit,
    vehicleHistory,
    direct,
}: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const args = { permit: permit.id };
    const { can, recommendation } = permit;

    const hasAction =
        can.startReview ||
        can.recommend ||
        can.requestChanges ||
        can.approve ||
        can.reject ||
        can.revoke ||
        can.reissue;

    // Why someone who may approve cannot decide yet. Display only: the server enforces it.
    const isApprover = (auth.permissions ?? []).includes('permits.approve');
    const blockedDecision =
        permit.status === 'under_review' && isApprover && !can.approve
            ? recommendation
                ? t('permits.staff.own_recommendation')
                : t('permits.staff.awaiting_recommendation')
            : null;

    return (
        <StaffPermitFrame
            permit={permit}
            vehicleHistory={vehicleHistory}
            title={t('permits.staff.desks.decision_title')}
            description={t('permits.staff.desks.decision_intro')}
            rolePage={!direct}
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div className="space-y-6">
                    <PermitDetailsCard permit={permit} />
                    <VehicleHistoryCard vehicleHistory={vehicleHistory} />
                </div>

                <section
                    aria-labelledby="decision-title"
                    className="h-fit space-y-4 lg:sticky lg:top-6"
                >
                    <h2 id="decision-title" className="text-section-title">
                        {t('permits.staff.decision')}
                    </h2>

                    {permit.letter && <PermitLetter letter={permit.letter} />}
                    {/* What the Reviewers found, read before the decision below. */}
                    {permit.reviews &&
                        (permit.reviews.length > 0 ||
                            permit.status === 'under_review') && (
                            <ReviewList reviews={permit.reviews} />
                        )}

                    {blockedDecision && (
                        <p className="rounded-card border border-border bg-card p-5 text-sm font-medium">
                            {blockedDecision}
                        </p>
                    )}

                    {!hasAction && !blockedDecision && (
                        <p className="rounded-card border border-border bg-card p-5 text-sm text-muted-foreground">
                            {t('permits.staff.no_action')}
                        </p>
                    )}

                    {can.startReview && (
                        <StaffAction
                            id="start-review"
                            title={t('permits.staff.start_review.title')}
                            hint={t('permits.staff.start_review.hint')}
                            action={startReview.url(args)}
                            submitLabel={t('permits.staff.start_review.submit')}
                        />
                    )}

                    {can.recommend && (
                        <ReviewForm action={recommend.url(args)} />
                    )}

                    <DecisionActions
                        urls={{
                            approve: can.approve
                                ? approve.url(args)
                                : undefined,
                            requestChanges: can.requestChanges
                                ? requestChanges.url(args)
                                : undefined,
                            reject: can.reject ? reject.url(args) : undefined,
                        }}
                    />

                    {permit.document_urls && (can.reissue || can.revoke) && (
                        <PermitDocumentLinks
                            urls={permit.document_urls}
                            variant="outline"
                            stacked
                        />
                    )}

                    {can.reissue && (
                        <StaffAction
                            id="reissue"
                            title={t('permits.staff.reissue.title')}
                            hint={t('permits.staff.reissue.hint')}
                            action={reissue.url(args)}
                            submitLabel={t('permits.staff.reissue.submit')}
                            note="required"
                            noteLabel={t('permits.staff.reissue.reason')}
                        />
                    )}

                    {can.revoke && (
                        <StaffAction
                            id="revoke"
                            title={t('permits.staff.revoke.title')}
                            hint={t('permits.staff.revoke.hint')}
                            action={revoke.url(args)}
                            submitLabel={t('permits.staff.revoke.submit')}
                            note="required"
                            noteLabel={t('permits.staff.revoke.reason')}
                            destructive
                        />
                    )}
                </section>
            </div>
        </StaffPermitFrame>
    );
}
