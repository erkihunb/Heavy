import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Table, TBody, Td, Th, THead, Tr } from '@/components/data-table';
import { DualDate } from '@/components/dual-date';
import { LetterStatusBadge } from '@/components/letter-status-badge';
import { formatBirr } from '@/components/permit-intake/smart';
import { PlateChip } from '@/components/plate-chip';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Permit } from '@/types';
import type { PermitStatus } from '@/types/enums';

/** One vehicle of the letter (App\Actions\Letters\LetterPermits::row). */
export type LetterVehicle = {
    id: string;
    status: PermitStatus;
    number: string | null;
    plate: string;
    type: string | null;
    trailers: string[];
    url: string | null;
    current: boolean;
    /** Santim; null until the permit is approved. */
    fee_cents?: number | null;
};

/**
 * The vehicles of a request letter, each with where its own permit stands;
 * this application's row is marked.
 */
function LetterVehiclesTable({
    vehicles,
    letterUrl,
}: {
    vehicles: LetterVehicle[];
    letterUrl?: string | null;
}) {
    const { t } = useTranslation();
    // The letter is paid for once: its vehicles waiting for that payment, added up.
    const unpaid = vehicles.filter(
        (vehicle) => vehicle.status === 'awaiting_payment',
    );
    const unpaidTotal = unpaid.reduce(
        (sum, vehicle) => sum + (vehicle.fee_cents ?? 0),
        0,
    );

    return (
        <div className="space-y-2">
            <div className="overflow-hidden rounded-card border border-border">
                <Table>
                    <THead>
                        <tr>
                            <Th>{t('vehicles.fields.plate')}</Th>
                            <Th>{t('vehicles.fields.vehicle_type')}</Th>
                            <Th>{t('letters.table.status')}</Th>
                            <Th>{t('letters.table.number')}</Th>
                        </tr>
                    </THead>
                    <TBody>
                        {vehicles.map((vehicle) => (
                            <Tr
                                key={vehicle.id}
                                aria-current={vehicle.current || undefined}
                                className={cn(
                                    vehicle.current && 'bg-primary/5',
                                )}
                            >
                                <Td>
                                    <PlateChip
                                        plate={vehicle.plate}
                                        className="px-2 py-0.5 text-sm"
                                    />
                                    {vehicle.trailers.length > 0 && (
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            + {vehicle.trailers.join(', ')}
                                        </span>
                                    )}
                                </Td>
                                <Td muted>{vehicle.type ?? '—'}</Td>
                                <Td>
                                    <LetterStatusBadge
                                        status={vehicle.status}
                                    />
                                </Td>
                                <Td className="font-mono text-xs">
                                    {vehicle.number ?? '—'}
                                </Td>
                            </Tr>
                        ))}
                    </TBody>
                </Table>
            </div>
            {unpaid.length > 0 && (
                <p className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-warning/40 bg-warning-soft px-3 py-2 text-sm">
                    <span className="font-medium">
                        {t('letters.payment_line', {
                            count: unpaid.length,
                            total: formatBirr(unpaidTotal),
                        })}
                    </span>
                    {letterUrl && (
                        <Link
                            href={letterUrl}
                            className="font-medium text-primary hover:underline"
                        >
                            {t('letters.go_pay')}
                        </Link>
                    )}
                </p>
            )}
        </div>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 py-4 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-4">
            <dt className="text-eyebrow text-muted-foreground">{label}</dt>
            <dd className="min-w-0">{children}</dd>
        </div>
    );
}

/**
 * Everything an application says, as a definition list. Staff-only fields
 * appear when the server sent them.
 */
export function PermitDetails({
    permit,
    letterVehicles = [],
}: {
    permit: Permit;
    /** Every vehicle of the request letter, when it asked for several. */
    letterVehicles?: LetterVehicle[];
}) {
    const { t } = useTranslation();
    const inLetter = letterVehicles.length > 1;

    return (
        <dl className="divide-y divide-border">
            {permit.branch && (
                <Row label={t('permits.fields.branch')}>
                    {permit.branch.name}
                </Row>
            )}
            <Row label={t('permits.fields.permit_number')}>
                {permit.permit_number ? (
                    <span
                        translate="no"
                        className="font-extrabold tracking-wide tabular-nums"
                    >
                        {permit.permit_number}
                    </span>
                ) : (
                    <span className="text-muted-foreground">
                        {t('permits.not_numbered')}
                    </span>
                )}
            </Row>

            <Row label={t('permits.fields.permit_type')}>
                <span className="font-medium">
                    {t(`permits.types.${permit.permit_type}`)}
                </span>
            </Row>

            {permit.organization !== undefined && (
                <Row label={t('permits.fields.organization')}>
                    <span className="font-medium">{permit.organization}</span>
                </Row>
            )}

            {permit.applicant !== undefined && (
                <Row label={t('permits.fields.applicant')}>
                    {permit.applicant}
                </Row>
            )}

            <Row
                label={
                    inLetter
                        ? `${t('permits.fields.vehicles')} (${letterVehicles.length})`
                        : t('permits.fields.vehicles')
                }
            >
                {inLetter ? (
                    <LetterVehiclesTable
                        vehicles={letterVehicles}
                        letterUrl={permit.letter?.group_url}
                    />
                ) : (
                    <div className="overflow-hidden rounded-card border border-border">
                        <Table>
                            <THead>
                                <tr>
                                    <Th>{t('vehicles.fields.plate')}</Th>
                                    <Th>{t('permits.fields.role')}</Th>
                                    <Th>{t('vehicles.fields.vehicle_type')}</Th>
                                    <Th>{t('vehicles.fields.owner_name')}</Th>
                                </tr>
                            </THead>
                            <TBody>
                                {(permit.vehicles ?? []).map((vehicle) => (
                                    <Tr key={vehicle.id}>
                                        <Td>
                                            <PlateChip plate={vehicle.plate} />
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {t(
                                                `permits.vehicle_roles.${vehicle.role}`,
                                            )}
                                        </Td>
                                        <Td muted>
                                            {vehicle.vehicle_type?.name ?? '—'}
                                        </Td>
                                        <Td>{vehicle.owner_name}</Td>
                                    </Tr>
                                ))}
                            </TBody>
                        </Table>
                    </div>
                )}
            </Row>

            <Row label={t('permits.fields.valid_from')}>
                <DualDate date={permit.valid_from} />
            </Row>

            <Row label={t('permits.fields.valid_to')}>
                <DualDate date={permit.valid_to} />
            </Row>

            <Row label={t('permits.fields.duration')}>
                <span className="tabular-nums">
                    {t('permits.duration_days', {
                        count: permit.duration_days,
                    })}
                </span>
            </Row>
            <Row label={t('permits.hours.title')}>
                {permit.hours
                    ? permit.hours.label
                    : t('permits.hours.any_time')}
            </Row>

            {permit.fee && (
                <Row label={t('payments.fee')}>
                    <span className="font-extrabold tabular-nums">
                        {permit.fee.formatted}
                    </span>
                </Row>
            )}

            <Row label={t('permits.fields.reason')}>
                <p className="leading-relaxed whitespace-pre-line">
                    {permit.reason}
                </p>
            </Row>

            {permit.route && (
                <Row label={t('permits.fields.route')}>
                    <p className="leading-relaxed">{permit.route}</p>
                </Row>
            )}

            {permit.submitted_at && (
                <Row label={t('permits.fields.submitted')}>
                    <DualDate date={permit.submitted_at} />
                </Row>
            )}
        </dl>
    );
}
