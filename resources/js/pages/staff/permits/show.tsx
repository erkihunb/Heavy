import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, FileDown, TriangleAlert } from 'lucide-react';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { PermitDetails } from '@/components/permit-details';
import { PermitTimeline } from '@/components/permit-timeline';
import { PlateChip } from '@/components/plate-chip';
import { StaffAction } from '@/components/staff-action';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import {
    approve,
    index,
    recommend,
    reissue,
    reject,
    requestChanges,
    revoke,
    show,
    startReview,
} from '@/routes/staff/permits';
import type { BreadcrumbItem, Permit, VehicleHistory } from '@/types';

type Props = {
    permit: Permit;
    vehicleHistory: VehicleHistory[];
};

export default function StaffPermitsShow({ permit, vehicleHistory }: Props) {
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

    // Why an Approver cannot decide yet. Display only: the server enforces it.
    const isApprover =
        auth.user.roles.includes('approver') ||
        auth.user.roles.includes('admin');
    const blockedDecision =
        permit.status === 'under_review' && isApprover && !can.approve
            ? recommendation
                ? t('permits.staff.own_recommendation')
                : t('permits.staff.awaiting_recommendation')
            : null;

    const conflicts = vehicleHistory.filter((vehicle) => vehicle.has_conflict);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('permits.staff.queue_title'), href: index() },
        {
            title: permit.permit_number ?? t('permits.staff.detail_title'),
            href: show(args),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.staff.detail_title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('permits.staff.detail_title')}
                    description={permit.organization}
                    action={<StatusBadge status={permit.effective_status} />}
                />

                {conflicts.map((vehicle) => (
                    <p
                        key={vehicle.vehicle_id}
                        role="alert"
                        className="flex items-start gap-3 rounded-xl border-2 border-destructive bg-card px-4 py-3 font-medium"
                    >
                        <TriangleAlert
                            aria-hidden="true"
                            className="mt-0.5 size-5 shrink-0 text-destructive"
                        />
                        {t('permits.staff.history.conflict_warning', {
                            plate: vehicle.plate,
                        })}
                    </p>
                ))}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <div className="space-y-6">
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
                        </section>

                        <section
                            aria-labelledby="history-title"
                            className="space-y-5 rounded-2xl border border-border bg-card p-6 sm:p-8"
                        >
                            <h2
                                id="history-title"
                                className="text-xl text-display"
                            >
                                {t('permits.staff.history.title')}
                            </h2>
                            {vehicleHistory.map((vehicle) => (
                                <div
                                    key={vehicle.vehicle_id}
                                    className="space-y-3"
                                >
                                    <PlateChip plate={vehicle.plate} />
                                    {vehicle.permits.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            {t('permits.staff.history.none')}
                                        </p>
                                    ) : (
                                        <ul className="divide-y divide-border rounded-xl border border-border">
                                            {vehicle.permits.map((other) => (
                                                <li
                                                    key={other.id}
                                                    className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 text-sm"
                                                >
                                                    <StatusBadge
                                                        status={
                                                            other.effective_status
                                                        }
                                                    />
                                                    <Link
                                                        href={show({
                                                            permit: other.id,
                                                        })}
                                                        translate="no"
                                                        className="font-bold tabular-nums underline underline-offset-4"
                                                    >
                                                        {other.permit_number ??
                                                            t(
                                                                `permits.types.${other.permit_type}`,
                                                            )}
                                                    </Link>
                                                    <span className="flex flex-wrap items-baseline gap-x-2">
                                                        <DualDate
                                                            date={
                                                                other.valid_from
                                                            }
                                                        />
                                                        <span aria-hidden="true">
                                                            –
                                                        </span>
                                                        <DualDate
                                                            date={
                                                                other.valid_to
                                                            }
                                                        />
                                                    </span>
                                                    {other.conflicts && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-destructive px-2.5 py-0.5 text-xs font-bold text-white">
                                                            <TriangleAlert
                                                                aria-hidden="true"
                                                                className="size-3.5"
                                                            />
                                                            {t(
                                                                'permits.staff.history.conflict',
                                                            )}
                                                        </span>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            ))}
                        </section>

                        <section
                            aria-labelledby="timeline-title"
                            className="rounded-2xl border border-border bg-card p-6 sm:p-8"
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

                    <section
                        aria-labelledby="decision-title"
                        className="h-fit space-y-4 lg:sticky lg:top-6"
                    >
                        <h2
                            id="decision-title"
                            className="text-xl text-display"
                        >
                            {t('permits.staff.decision')}
                        </h2>

                        {recommendation && (
                            <div className="space-y-2 rounded-xl border border-border bg-muted p-5">
                                <p className="text-eyebrow text-muted-foreground">
                                    {t('permits.staff.recommend.current')}
                                </p>
                                <p className="text-lg font-bold">
                                    {t(
                                        `permits.staff.recommend.${recommendation.decision}`,
                                    )}
                                </p>
                                <p className="flex flex-wrap items-baseline gap-x-2 text-sm text-muted-foreground">
                                    {recommendation.by && (
                                        <span>
                                            {t('permits.timeline.by', {
                                                name: recommendation.by,
                                            })}
                                        </span>
                                    )}
                                    {recommendation.at && (
                                        <DualDate date={recommendation.at} />
                                    )}
                                </p>
                                {recommendation.note && (
                                    <p className="text-sm leading-relaxed whitespace-pre-line">
                                        {recommendation.note}
                                    </p>
                                )}
                            </div>
                        )}

                        {blockedDecision && (
                            <p className="rounded-xl border border-border bg-card p-5 text-sm font-medium">
                                {blockedDecision}
                            </p>
                        )}

                        {!hasAction && !blockedDecision && (
                            <p className="rounded-xl border border-border bg-card p-5 text-sm text-muted-foreground">
                                {t('permits.staff.no_action')}
                            </p>
                        )}

                        {can.startReview && (
                            <StaffAction
                                id="start-review"
                                title={t('permits.staff.start_review.title')}
                                hint={t('permits.staff.start_review.hint')}
                                action={startReview.url(args)}
                                submitLabel={t(
                                    'permits.staff.start_review.submit',
                                )}
                            />
                        )}

                        {can.recommend && (
                            <StaffAction
                                id="recommend"
                                title={t('permits.staff.recommend.title')}
                                hint={t('permits.staff.recommend.hint')}
                                action={recommend.url(args)}
                                submitLabel={t(
                                    'permits.staff.recommend.submit',
                                )}
                                note="optional"
                                noteLabel={t('permits.fields.note')}
                                withRecommendation
                            />
                        )}

                        {can.approve && (
                            <StaffAction
                                id="approve"
                                title={t('permits.staff.approve.title')}
                                hint={t('permits.staff.approve.hint')}
                                action={approve.url(args)}
                                submitLabel={t('permits.staff.approve.submit')}
                                note="optional"
                            />
                        )}

                        {can.requestChanges && (
                            <StaffAction
                                id="request-changes"
                                title={t('permits.staff.request_changes.title')}
                                hint={t('permits.staff.request_changes.hint')}
                                action={requestChanges.url(args)}
                                submitLabel={t(
                                    'permits.staff.request_changes.submit',
                                )}
                                note="required"
                                noteLabel={t(
                                    'permits.staff.note_for_applicant',
                                )}
                            />
                        )}

                        {can.reject && (
                            <StaffAction
                                id="reject"
                                title={t('permits.staff.reject.title')}
                                hint={t('permits.staff.reject.hint')}
                                action={reject.url(args)}
                                submitLabel={t('permits.staff.reject.submit')}
                                note="required"
                                noteLabel={t(
                                    'permits.staff.note_for_applicant',
                                )}
                                destructive
                            />
                        )}

                        {permit.document_url && (
                            <Button
                                asChild
                                variant="outline"
                                className="w-full"
                            >
                                <a
                                    href={permit.document_url}
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <FileDown aria-hidden="true" />
                                    {t('permits.actions.download')}
                                </a>
                            </Button>
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
