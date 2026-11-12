import type { ReactNode } from 'react';
import { Table, TBody, Td, Th, THead, Tr } from '@/components/data-table';
import { DualDate } from '@/components/dual-date';
import { PlateChip } from '@/components/plate-chip';
import { useTranslation } from '@/lib/i18n';
import type { Permit } from '@/types';

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
export function PermitDetails({ permit }: { permit: Permit }) {
    const { t } = useTranslation();

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

            <Row label={t('permits.fields.vehicles')}>
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

            {permit.submitted_at && (
                <Row label={t('permits.fields.submitted')}>
                    <DualDate date={permit.submitted_at} />
                </Row>
            )}
        </dl>
    );
}
