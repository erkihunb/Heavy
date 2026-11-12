import {
    Ban,
    CircleCheck,
    CircleDashed,
    ClockAlert,
    Hourglass,
    SearchX,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { DualDate } from '@/types';
import type { ScanResult } from '@/types/enums';

const PRESENTATION: Record<
    ScanResult,
    { icon: LucideIcon; className: string }
> = {
    valid: {
        icon: CircleCheck,
        className: 'border-success bg-success text-success-foreground',
    },
    outside_hours: {
        icon: ClockAlert,
        className: 'border-warning bg-warning text-white',
    },
    not_yet_valid: {
        icon: Hourglass,
        className: 'border-highlight bg-highlight text-highlight-foreground',
    },
    expired: {
        icon: CircleDashed,
        className: 'border-border bg-muted text-foreground',
    },
    revoked: {
        icon: Ban,
        className: 'border-destructive bg-destructive text-white',
    },
    not_found: {
        icon: SearchX,
        className: 'border-destructive bg-card text-foreground',
    },
};

/**
 * The big, colour-coded answer of a verification (spec §7). The icon and the
 * words carry the meaning; the colour only repeats it.
 */
export function VerificationResult({
    result,
    checkedAt,
    checkedTime,
    checkedTimeEthiopian,
    hours,
}: {
    result: ScanResult;
    checkedAt: DualDate;
    checkedTime: string;
    /** The time of the check on the Ethiopian clock. */
    checkedTimeEthiopian?: string;
    /** The permit's movement hours, shown when it is checked outside them. */
    hours?: { label: string } | null;
}) {
    const { t } = useTranslation();
    const { icon: Icon, className } = PRESENTATION[result];

    return (
        <section
            aria-labelledby="result-title"
            data-result={result}
            className={cn(
                'rounded-panel border-4 p-6 text-center sm:p-8',
                className,
            )}
        >
            <Icon aria-hidden="true" className="mx-auto size-14" />
            <h1
                id="result-title"
                className="mt-3 text-4xl font-black tracking-wide uppercase"
            >
                {t(`verification.results.${result}.title`)}
            </h1>
            <p className="mt-2 text-lg font-medium">
                {t(`verification.results.${result}.body`)}
            </p>
            {result === 'outside_hours' && hours && (
                <dl className="mx-auto mt-4 grid max-w-md gap-2 rounded-card bg-black/15 px-4 py-3 text-left text-base sm:grid-cols-2">
                    <div>
                        <dt className="text-xs font-semibold uppercase opacity-80">
                            {t('verification.hours.allowed')}
                        </dt>
                        <dd className="font-bold">{hours.label}</dd>
                    </div>
                    <div>
                        <dt className="text-xs font-semibold uppercase opacity-80">
                            {t('verification.hours.now')}
                        </dt>
                        <dd className="font-bold">
                            {checkedTimeEthiopian
                                ? `${checkedTimeEthiopian} (${checkedTime})`
                                : checkedTime}
                        </dd>
                    </div>
                </dl>
            )}
            <p className="mt-3 text-sm opacity-80">
                {t('verification.checked_at', {
                    date: checkedAt.ethiopian_label,
                    time: checkedTime,
                })}
            </p>
        </section>
    );
}
