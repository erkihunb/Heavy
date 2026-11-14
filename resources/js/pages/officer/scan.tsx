import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Table, TBody, Td, Th, THead, Tr } from '@/components/data-table';
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
    checked_time_ethiopian: string;
    permit: {
        id: string;
        permit_number: string;
        permit_type: PermitType;
        vehicles: { plate: string; role: VehicleRole; vehicle_type: string }[];
        organization: string | null;
        valid_from: DualDateValue;
        valid_to: DualDateValue;
        time_window_rules: string | null;
        /** The hours of the day it lets the vehicles move; null for any time of day. */
        hours: { from: string; to: string; label: string } | null;
        history: ({
            from: PermitStatus | null;
            to: PermitStatus;
            note: string | null;
        } & Moment)[];
        /** Every check of this permit so far, this one included. */
        scan_count: number;
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
                    checkedTimeEthiopian={verification.checked_time_ethiopian}
                    hours={permit?.hours}
                />

                {permit && (
                    <>
                        <section
                            aria-label={t('verification.details')}
                            className="rounded-card border border-border bg-card p-5"
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
                                    <div className="overflow-hidden rounded-card border border-border">
                                        <Table>
                                            <THead>
                                                <tr>
                                                    <Th>
                                                        {t(
                                                            'vehicles.fields.plate',
                                                        )}
                                                    </Th>
                                                    <Th>
                                                        {t(
                                                            'permits.fields.role',
                                                        )}
                                                    </Th>
                                                    <Th>
                                                        {t(
                                                            'vehicles.fields.vehicle_type',
                                                        )}
                                                    </Th>
                                                </tr>
                                            </THead>
                                            <TBody>
                                                {permit.vehicles.map(
                                                    (vehicle) => (
                                                        <Tr key={vehicle.plate}>
                                                            <Td>
                                                                <PlateChip
                                                                    plate={
                                                                        vehicle.plate
                                                                    }
                                                                />
                                                            </Td>
                                                            <Td className="whitespace-nowrap">
                                                                {t(
                                                                    `permits.vehicle_roles.${vehicle.role}`,
                                                                )}
                                                            </Td>
                                                            <Td muted>
                                                                {
                                                                    vehicle.vehicle_type
                                                                }
                                                            </Td>
                                                        </Tr>
                                                    ),
                                                )}
                                            </TBody>
                                        </Table>
                                    </div>
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
                                <Row label={t('verification.hours.title')}>
                                    <span className="font-semibold">
                                        {permit.hours
                                            ? permit.hours.label
                                            : t('permits.hours.any_time')}
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
                            className="space-y-3 rounded-card border border-border bg-card p-5"
                        >
                            <h2
                                id="reports-title"
                                className="text-section-title"
                            >
                                {t('verification.officer.reports')}
                            </h2>
                            {permit.reports.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('verification.officer.no_reports')}
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-card border border-border">
                                    <Table>
                                        <THead>
                                            <tr>
                                                <Th>{t('common.date')}</Th>
                                                <Th>
                                                    {t(
                                                        'verification.officer.report.note',
                                                    )}
                                                </Th>
                                                <Th>
                                                    {t(
                                                        'permits.timeline.actor',
                                                    )}
                                                </Th>
                                            </tr>
                                        </THead>
                                        <TBody>
                                            {permit.reports.map(
                                                (report, index) => (
                                                    <Tr
                                                        key={index}
                                                        className="align-top"
                                                    >
                                                        <Td className="align-top whitespace-nowrap">
                                                            <When
                                                                moment={report}
                                                            />
                                                        </Td>
                                                        <Td className="align-top whitespace-pre-line">
                                                            {report.note}
                                                        </Td>
                                                        <Td
                                                            muted
                                                            className="align-top"
                                                        >
                                                            {report.by}
                                                        </Td>
                                                    </Tr>
                                                ),
                                            )}
                                        </TBody>
                                    </Table>
                                </div>
                            )}
                        </section>

                        <section
                            aria-labelledby="history-title"
                            className="space-y-3 rounded-card border border-border bg-card p-5"
                        >
                            <h2
                                id="history-title"
                                className="text-section-title"
                            >
                                {t('verification.officer.history')}
                            </h2>
                            <div className="overflow-hidden rounded-card border border-border">
                                <Table>
                                    <THead>
                                        <tr>
                                            <Th>{t('common.date')}</Th>
                                            <Th>
                                                {t('permits.timeline.step')}
                                            </Th>
                                            <Th>
                                                {t('permits.timeline.note')}
                                            </Th>
                                        </tr>
                                    </THead>
                                    <TBody>
                                        {permit.history.map((step, index) => (
                                            <Tr
                                                key={index}
                                                className="align-top"
                                            >
                                                <Td className="align-top whitespace-nowrap">
                                                    <When moment={step} />
                                                </Td>
                                                <Td className="align-top font-medium">
                                                    {step.from === null
                                                        ? t(
                                                              'permits.timeline.created',
                                                          )
                                                        : step.from === step.to
                                                          ? t(
                                                                'verification.officer.reissued',
                                                            )
                                                          : t(
                                                                `permits.status.${step.to}`,
                                                            )}
                                                </Td>
                                                <Td className="align-top whitespace-pre-line">
                                                    {step.note ?? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </Td>
                                            </Tr>
                                        ))}
                                    </TBody>
                                </Table>
                            </div>
                        </section>

                        <section
                            aria-labelledby="scans-title"
                            className="space-y-3 rounded-card border border-border bg-card p-5"
                        >
                            <h2 id="scans-title" className="text-section-title">
                                {t('verification.officer.scans')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('verification.officer.scan_count', {
                                    count: permit.scan_count,
                                })}
                            </p>
                            <div className="overflow-hidden rounded-card border border-border">
                                <Table>
                                    <THead>
                                        <tr>
                                            <Th>{t('common.date')}</Th>
                                            <Th>{t('common.status')}</Th>
                                            <Th>
                                                {t(
                                                    'verification.officer.channel',
                                                )}
                                            </Th>
                                            <Th>
                                                {t('permits.timeline.actor')}
                                            </Th>
                                        </tr>
                                    </THead>
                                    <TBody>
                                        {permit.scans.map((scan, index) => (
                                            <Tr key={index}>
                                                <Td className="whitespace-nowrap">
                                                    <When moment={scan} />
                                                </Td>
                                                <Td className="font-medium">
                                                    {t(
                                                        `verification.results.${scan.result}.title`,
                                                    )}
                                                </Td>
                                                <Td>
                                                    {t(
                                                        `verification.officer.channels.${scan.channel}`,
                                                    )}
                                                </Td>
                                                <Td muted>
                                                    {scan.by ??
                                                        t(
                                                            'verification.officer.scan_by_public',
                                                        )}
                                                </Td>
                                            </Tr>
                                        ))}
                                    </TBody>
                                </Table>
                            </div>
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
