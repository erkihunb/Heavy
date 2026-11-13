import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { Table, TBody, Td, Th, THead, Tr } from '@/components/data-table';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { PermitDetails } from '@/components/permit-details';
import { PlateChip } from '@/components/plate-chip';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/staff/permits';
import type { BreadcrumbItem, Permit, VehicleHistory } from '@/types';

/** One role's page for an application (PermitReviewController::desks()). */
export type Desk = {
    key: 'decision' | 'payment' | 'issue';
    url: string;
    /** Something on that page waits for this person now. */
    pending: boolean;
};

/**
 * The frame of the staff pages of one application: the read-only page and
 * each role's page (decision, payment, issue). The heading names the page,
 * the organization and the status; a warning stands above everything when a
 * vehicle already holds a permit for overlapping dates.
 */
export function StaffPermitFrame({
    permit,
    vehicleHistory,
    title,
    description,
    rolePage = false,
    children,
}: {
    permit: Permit;
    vehicleHistory: VehicleHistory[];
    title: string;
    description?: string;
    /** A role's page: the breadcrumbs and the back button lead to the application page. */
    rolePage?: boolean;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const args = { permit: permit.id };
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
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={title}
                    description={
                        description
                            ? `${permit.organization} · ${description}`
                            : permit.organization
                    }
                    action={<StatusBadge status={permit.effective_status} />}
                />

                {conflicts.map((vehicle) => (
                    <p
                        key={vehicle.vehicle_id}
                        role="alert"
                        className="flex items-start gap-3 rounded-card border-2 border-destructive bg-card px-4 py-3 font-medium"
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

                {children}

                <Button asChild variant="outline" className="w-fit">
                    <Link href={rolePage ? show(args) : index()}>
                        <ArrowLeft aria-hidden="true" />
                        {rolePage
                            ? t('permits.staff.desks.back')
                            : t('permits.actions.back')}
                    </Link>
                </Button>
            </div>
        </AppLayout>
    );
}

/** The registered application, read only. */
export function PermitDetailsCard({ permit }: { permit: Permit }) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby="details-title"
            className="rounded-card border border-border bg-card p-5 sm:p-6"
        >
            <h2 id="details-title" className="mb-2 text-section-title">
                {t('permits.review.details')}
            </h2>
            <PermitDetails permit={permit} />
        </section>
    );
}

/** Every permit each vehicle on the application has held or asked for. */
export function VehicleHistoryCard({
    vehicleHistory,
}: {
    vehicleHistory: VehicleHistory[];
}) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby="history-title"
            className="space-y-5 rounded-card border border-border bg-card p-5 sm:p-6"
        >
            <h2 id="history-title" className="text-section-title">
                {t('permits.staff.history.title')}
            </h2>
            {vehicleHistory.map((vehicle) => (
                <div key={vehicle.vehicle_id} className="space-y-3">
                    <PlateChip plate={vehicle.plate} />
                    {vehicle.permits.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('permits.staff.history.none')}
                        </p>
                    ) : (
                        <div className="overflow-hidden rounded-card border border-border">
                            <Table>
                                <THead>
                                    <tr>
                                        <Th>{t('permits.fields.status')}</Th>
                                        <Th>
                                            {t('permits.fields.permit_number')}
                                        </Th>
                                        <Th>{t('permits.fields.validity')}</Th>
                                        <Th>
                                            <span className="sr-only">
                                                {t(
                                                    'permits.staff.history.conflict',
                                                )}
                                            </span>
                                        </Th>
                                    </tr>
                                </THead>
                                <TBody>
                                    {vehicle.permits.map((other) => (
                                        <Tr key={other.id}>
                                            <Td>
                                                <StatusBadge
                                                    status={
                                                        other.effective_status
                                                    }
                                                />
                                            </Td>
                                            <Td className="whitespace-nowrap">
                                                <Link
                                                    href={show({
                                                        permit: other.id,
                                                    })}
                                                    translate="no"
                                                    className="font-semibold text-primary tabular-nums underline-offset-4 hover:underline"
                                                >
                                                    {other.permit_number ??
                                                        t(
                                                            `permits.types.${other.permit_type}`,
                                                        )}
                                                </Link>
                                            </Td>
                                            <Td className="whitespace-nowrap">
                                                <span className="flex flex-wrap items-baseline gap-x-1.5">
                                                    <DualDate
                                                        date={other.valid_from}
                                                    />
                                                    <span aria-hidden="true">
                                                        –
                                                    </span>
                                                    <DualDate
                                                        date={other.valid_to}
                                                    />
                                                </span>
                                            </Td>
                                            <Td>
                                                {other.conflicts && (
                                                    <span className="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800 ring-1 ring-red-200 ring-inset dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900">
                                                        <TriangleAlert
                                                            aria-hidden="true"
                                                            className="size-3.5"
                                                        />
                                                        {t(
                                                            'permits.staff.history.conflict',
                                                        )}
                                                    </span>
                                                )}
                                            </Td>
                                        </Tr>
                                    ))}
                                </TBody>
                            </Table>
                        </div>
                    )}
                </div>
            ))}
        </section>
    );
}

/** The Reviewer's recommendation, when two-step approval asked for one. */
export function RecommendationCard({ permit }: { permit: Permit }) {
    const { t } = useTranslation();
    const { recommendation } = permit;

    if (!recommendation) {
        return null;
    }

    return (
        <div className="space-y-2 rounded-card border border-border bg-muted p-5">
            <p className="text-eyebrow text-muted-foreground">
                {t('permits.staff.recommend.current')}
            </p>
            <p className="text-section-title">
                {t(`permits.staff.recommend.${recommendation.decision}`)}
            </p>
            <p className="flex flex-wrap items-baseline gap-x-2 text-sm text-muted-foreground">
                {recommendation.by && (
                    <span>
                        {t('permits.timeline.by', { name: recommendation.by })}
                    </span>
                )}
                {recommendation.at && <DualDate date={recommendation.at} />}
            </p>
            {recommendation.note && (
                <p className="text-sm leading-relaxed whitespace-pre-line">
                    {recommendation.note}
                </p>
            )}
        </div>
    );
}

/**
 * The role pages open to this person, on the read-only application page:
 * the one where something waits for them now stands out.
 */
export function DeskLinks({ desks }: { desks: Desk[] }) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby="desks-title"
            className="space-y-3 rounded-card border border-border bg-card p-5"
        >
            <div className="space-y-1">
                <h2 id="desks-title" className="text-section-title">
                    {t('permits.staff.desks.title')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {desks.length === 0
                        ? t('permits.staff.desks.none')
                        : t('permits.staff.desks.intro')}
                </p>
            </div>
            {desks.map((desk) => (
                <Link
                    key={desk.key}
                    href={desk.url}
                    className={cn(
                        'flex items-center justify-between gap-3 rounded-card border px-4 py-3 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        desk.pending
                            ? 'border-primary bg-primary/5 hover:bg-primary/10'
                            : 'border-border hover:bg-muted',
                    )}
                >
                    <span className="min-w-0">
                        <span className="block font-semibold">
                            {t(`permits.staff.desks.${desk.key}`)}
                        </span>
                        <span
                            className={cn(
                                'block text-xs',
                                desk.pending
                                    ? 'font-medium text-primary'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {desk.pending
                                ? t('permits.staff.desks.pending')
                                : t('permits.staff.desks.nothing')}
                        </span>
                    </span>
                    <ChevronRight
                        aria-hidden="true"
                        className="size-4 shrink-0"
                    />
                </Link>
            ))}
        </section>
    );
}
