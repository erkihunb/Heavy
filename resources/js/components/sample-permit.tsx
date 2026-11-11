import { BadgeCheck } from 'lucide-react';
import { DualDate } from '@/components/dual-date';
import { PlateChip } from '@/components/plate-chip';
import { useTranslation } from '@/lib/i18n';
import type { DualDate as DualDateValue } from '@/types';

// Finder-pattern style motif. Deliberately not a scannable QR code.
const QR_CELLS = [
    '1111111001101',
    '1000001010011',
    '1011101001100',
    '1011101011010',
    '1011101000111',
    '1000001010100',
    '1111111010101',
    '0000000011010',
    '1101011100111',
    '0110100101001',
    '1010111010110',
    '0101001101011',
    '1110110010101',
];

function QrMotif({ label }: { label: string }) {
    return (
        <svg
            role="img"
            aria-label={label}
            viewBox="0 0 13 13"
            shapeRendering="crispEdges"
            className="size-24 rounded-lg border border-border bg-white p-1.5 text-asphalt sm:size-28"
        >
            {QR_CELLS.flatMap((row, y) =>
                [...row].map((cell, x) =>
                    cell === '1' ? (
                        <rect
                            key={`${x}-${y}`}
                            x={x}
                            y={y}
                            width="1"
                            height="1"
                            fill="currentColor"
                        />
                    ) : null,
                ),
            )}
        </svg>
    );
}

/**
 * An illustration of what an issued permit looks like, for the landing page.
 * Every value is a placeholder and the card says so; the dates are real
 * calendar conversions supplied by the server.
 */
export function SamplePermit({
    validFrom,
    validTo,
}: {
    validFrom: DualDateValue;
    validTo: DualDateValue;
}) {
    const { t } = useTranslation();

    return (
        <figure className="relative">
            <div className="overflow-hidden rounded-3xl border border-border bg-card text-card-foreground shadow-raised-brand lg:-rotate-2">
                <div className="brand-bar" aria-hidden="true" />

                <div className="space-y-6 p-6 sm:p-7">
                    <div className="flex items-start justify-between gap-4">
                        <div className="space-y-1">
                            <p className="text-eyebrow text-muted-foreground">
                                {t('landing.sample.number_label')}
                            </p>
                            <p
                                translate="no"
                                className="text-lg font-extrabold tracking-wide tabular-nums"
                            >
                                TMA-HV-0000-000000
                            </p>
                        </div>
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-success px-3 py-1.5 text-sm font-bold text-success-foreground">
                            <BadgeCheck aria-hidden="true" className="size-4" />
                            {t('landing.sample.status')}
                        </span>
                    </div>

                    <div className="flex items-end justify-between gap-4">
                        <dl className="grid gap-4">
                            <div className="space-y-1.5">
                                <dt className="text-eyebrow text-muted-foreground">
                                    {t('landing.sample.plate_label')}
                                </dt>
                                <dd>
                                    <PlateChip plate="አአ-00-A00000" />
                                </dd>
                            </div>
                            <div className="space-y-1">
                                <dt className="text-eyebrow text-muted-foreground">
                                    {t('landing.sample.type_label')}
                                </dt>
                                <dd className="font-bold">
                                    {t('landing.sample.type_value')}
                                </dd>
                            </div>
                        </dl>
                        <QrMotif label={t('landing.sample.qr_label')} />
                    </div>

                    <div
                        className="lane-dash text-foreground/20"
                        aria-hidden="true"
                    />

                    <dl className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <dt className="text-eyebrow text-muted-foreground">
                                {t('landing.sample.valid_from')}
                            </dt>
                            <dd>
                                <DualDate date={validFrom} stacked />
                            </dd>
                        </div>
                        <div className="space-y-1">
                            <dt className="text-eyebrow text-muted-foreground">
                                {t('landing.sample.valid_to')}
                            </dt>
                            <dd>
                                <DualDate date={validTo} stacked />
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <figcaption className="mt-6 text-center text-sm text-muted-foreground lg:mt-8">
                <span className="font-semibold text-foreground">
                    {t('landing.sample.label')}
                </span>{' '}
                · {t('landing.sample.note')}
            </figcaption>
        </figure>
    );
}
