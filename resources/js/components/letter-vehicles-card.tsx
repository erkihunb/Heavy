import { Link } from '@inertiajs/react';
import { Layers } from 'lucide-react';
import { PlateChip } from '@/components/plate-chip';
import { StatusBadge } from '@/components/status-badge';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
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
};

/**
 * Every vehicle of the request letter this application came in with, when
 * it asked for several: where each one's permit stands, this one marked,
 * with a link to the letter's page where they are decided together.
 */
export function LetterVehiclesCard({
    vehicles,
    letterUrl,
}: {
    vehicles: LetterVehicle[];
    letterUrl?: string | null;
}) {
    const { t } = useTranslation();

    if (vehicles.length < 2) {
        return null;
    }

    return (
        <section
            aria-labelledby="letter-vehicles-title"
            className="overflow-hidden rounded-card border border-border bg-card"
        >
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                <h2 id="letter-vehicles-title" className="text-section-title">
                    {t('letters.table.title')}{' '}
                    <span className="text-muted-foreground tabular-nums">
                        ({vehicles.length})
                    </span>
                </h2>
                {letterUrl && (
                    <Link
                        href={letterUrl}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                    >
                        <Layers aria-hidden className="size-4" />
                        {t('letters.open')}
                    </Link>
                )}
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[36rem] text-left text-sm">
                    <thead className="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" className="px-5 py-2 font-medium">
                                {t('letters.table.plate')}
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                {t('letters.table.type')}
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                {t('letters.table.status')}
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                {t('letters.table.number')}
                            </th>
                            <th scope="col" className="px-5 py-2">
                                <span className="sr-only">
                                    {t('letters.table.open')}
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {vehicles.map((vehicle) => (
                            <tr
                                key={vehicle.id}
                                aria-current={vehicle.current || undefined}
                                className={cn(
                                    vehicle.current && 'bg-primary/5',
                                )}
                            >
                                <td className="px-5 py-2.5">
                                    <PlateChip
                                        plate={vehicle.plate}
                                        className="px-2 py-0.5 text-sm"
                                    />
                                    {vehicle.trailers.length > 0 && (
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            + {vehicle.trailers.join(', ')}
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-2.5">
                                    {vehicle.type ?? '—'}
                                </td>
                                <td className="px-3 py-2.5">
                                    <StatusBadge status={vehicle.status} />
                                </td>
                                <td className="px-3 py-2.5 font-mono text-xs">
                                    {vehicle.number ?? '—'}
                                </td>
                                <td className="px-5 py-2.5 text-right">
                                    {vehicle.current ? (
                                        <span className="text-xs font-semibold text-primary">
                                            {t('letters.table.current')}
                                        </span>
                                    ) : (
                                        vehicle.url && (
                                            <Link
                                                href={vehicle.url}
                                                className="text-sm font-medium text-primary hover:underline"
                                            >
                                                {t('letters.table.open')}
                                            </Link>
                                        )
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
