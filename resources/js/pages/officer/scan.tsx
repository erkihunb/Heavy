import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { DualDate } from '@/components/dual-date';
import { PlateChip } from '@/components/plate-chip';
import { StaffAction } from '@/components/staff-action';
import { Button } from '@/components/ui/button';
import { VerificationResult } from '@/components/verification-result';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { verify } from '@/routes/officer';
import { store as storeReport } from '@/routes/officer/permits/reports';
import type { BreadcrumbItem, DualDate as DualDateValue } from '@/types';
import type {
    PermitStatus,
    PermitType,
    ScanChannel,
    ScanResult,
    VehicleRole,
} from '@/types/enums';

type Moment = { date: DualDateValue; time: string };

/** Mirrors App\Http\Resources\OfficerPermitVerificationResource. */
type Verification = {
    result: ScanResult;
    checked_at: DualDateValue;
    checked_time: string;
    permit: {
        id: string;
        permit_number: string;
        permit_type: PermitType;
        vehicles: { plate: string; role: VehicleRole; vehicle_type: string }[];
        organization: string | null;
        valid_from: DualDateValue;
        valid_to: DualDateValue;
        time_window_rules: string | null;
        history: ({
            from: PermitStatus | null;
            to: PermitStatus;
            note: string | null;
        } & Moment)[];
        scans: ({
            result: ScanResult;
            channel: ScanChannel;
            by: string | null;
        } & Moment)[];
        reports: ({ note: string; by: string } & Moment)[];
    } | null;
};

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 py-3 sm:grid-cols-[10rem_minmax(0,1fr)]">
            <dt className="text-eyebrow text-muted-foreground">{label}</dt>
            <dd className="min-w-0">{children}</dd>
        </div>
    );
}

function When({ moment }: { moment: Moment }) {
    return (
        <span className="inline-flex flex-wrap items-baseline gap-x-2 text-sm text-muted-foreground">
            <DualDate date={moment.date} />
            <span className="tabular-nums">{moment.time}</span>
        </span>
    );
}

/**
 * What a traffic officer sees after scanning or looking up a permit: the
 * public answer, then the permit's history, recent checks and reports, and a
 * form to report a violation.
 */
export default function OfficerScan({
    verification,
}: {
    verification: Verification;
}) {
    const { t } = useTranslation();
    const { permit } = verification;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('verification.officer.nav'), href: verify() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('verification.officer.scan_title')} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6">
                <VerificationResult
                    result={verification.result}
                    checkedAt={verification.checked_at}
                    checkedTime={verification.checked_time}
                />

                {permit && (
                    <>
                        <section
                            aria-label={t('verification.details')}
                            className="rounded-2xl border border-border bg-card p-6"
                        >
                            <dl className="divide-y divide-border">
                                <Row
                                    label={t(
                                        'verification.fields.permit_number',
                                    )}
                                >
                                    <span
                                        translate="no"
                                        className="text-xl font-extrabold tracking-wide tabular-nums"
                                    >
                                        {permit.permit_number}
                                    </span>
                                </Row>
                                <Row
                                    label={t('verification.fields.permit_type')}
                                >
                                    <span className="font-bold">
                                        {t(
                                            `permits.types.${permit.permit_type}`,
                                        )}
                                    </span>
                                </Row>
                                <Row label={t('verification.fields.vehicles')}>
                                    <ul className="space-y-2">
                                        {permit.vehicles.map((vehicle) => (
                                            <li
                                                key={vehicle.plate}
                                                className="flex flex-wrap items-center gap-x-3 gap-y-1"
                                            >
                                                <PlateChip
                                                    plate={vehicle.plate}
                                                />
                                                <span className="text-sm text-muted-foreground">
                                                    {t(
                                                        `permits.vehicle_roles.${vehicle.role}`,
                                                    )}{' '}
                                                    · {vehicle.vehicle_type}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </Row>
                                {permit.organization && (
                                    <Row
                                        label={t(
                                            'verification.fields.organization',
                                        )}
                                    >
                                        {permit.organization}
                                    </Row>
                                )}
                                <Row label={t('verification.fields.validity')}>
                                    <span className="flex flex-wrap items-baseline gap-x-2">
                                        <DualDate date={permit.valid_from} />
                                        <span aria-hidden="true">–</span>
                                        <DualDate date={permit.valid_to} />
                                    </span>
                                </Row>
                                {permit.time_window_rules && (
                                    <Row label={t('verification.fields.rules')}>
                                        <p className="leading-relaxed whitespace-pre-line">
                                            {permit.time_window_rules}
                                        </p>
                                    </Row>
                                )}
                            </dl>
                        </section>

                        <StaffAction
                            id="report"
                            title={t('verification.officer.report.title')}
                            hint={t('verification.officer.report.hint')}
                            action={storeReport.url({ permit: permit.id })}
                            submitLabel={t(
                                'verification.officer.report.submit',
                            )}
                            note="required"
                            noteLabel={t('verification.officer.report.note')}
                        />

                        <section
                            aria-labelledby="reports-title"
                            className="space-y-3 rounded-2xl border border-border bg-card p-6"
                        >
                            <h2
                                id="reports-title"
                                className="text-lg font-bold"
                            >
                                {t('verification.officer.reports')}
                            </h2>
                            {permit.reports.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('verification.officer.no_reports')}
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {permit.reports.map((report, index) => (
                                        <li
                                            key={index}
                                            className="space-y-1 rounded-xl bg-muted px-4 py-3"
                                        >
                                            <p className="whitespace-pre-line">
                                                {report.note}
                                            </p>
                                            <p className="flex flex-wrap gap-x-2 text-sm text-muted-foreground">
                                                <span>{report.by}</span>
                                                <When moment={report} />
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section
                            aria-labelledby="history-title"
                            className="space-y-3 rounded-2xl border border-border bg-card p-6"
                        >
                            <h2
                                id="history-title"
                                className="text-lg font-bold"
                            >
                                {t('verification.officer.history')}
                            </h2>
                            <ol className="space-y-3">
                                {permit.history.map((step, index) => (
                                    <li key={index} className="space-y-1">
                                        <p className="font-semibold">
                                            {step.from === null
                                                ? t('permits.timeline.created')
                                                : step.from === step.to
                                                  ? t(
                                                        'verification.officer.reissued',
                                                    )
                                                  : t(
                                                        `permits.status.${step.to}`,
                                                    )}
                                        </p>
                                        <When moment={step} />
                                        {step.note && (
                                            <p className="rounded-lg bg-muted px-3 py-2 text-sm whitespace-pre-line">
                                                {step.note}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </section>

                        <section
                            aria-labelledby="scans-title"
                            className="space-y-3 rounded-2xl border border-border bg-card p-6"
                        >
                            <h2 id="scans-title" className="text-lg font-bold">
                                {t('verification.officer.scans')}
                            </h2>
                            <ul className="divide-y divide-border text-sm">
                                {permit.scans.map((scan, index) => (
                                    <li
                                        key={index}
                                        className="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2"
                                    >
                                        <span className="font-bold">
                                            {t(
                                                `verification.results.${scan.result}.title`,
                                            )}
                                        </span>
                                        <span>
                                            {t(
                                                `verification.officer.channels.${scan.channel}`,
                                            )}
                                        </span>
                                        <span>
                                            {scan.by ??
                                                t(
                                                    'verification.officer.scan_by_public',
                                                )}
                                        </span>
                                        <When moment={scan} />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </>
                )}

                <Button asChild variant="outline" className="w-fit">
                    <Link href={verify()}>
                        <ArrowLeft aria-hidden="true" />
                        {t('verification.check_another')}
                    </Link>
                </Button>
            </div>
        </AppLayout>
    );
}
