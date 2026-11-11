import { cn } from '@/lib/utils';
import type { DualDate as DualDateValue } from '@/types';

/**
 * A date shown the way the whole system shows dates: the Ethiopian calendar
 * first, the Gregorian date as secondary text. Both come from the server.
 */
export function DualDate({
    date,
    className,
    secondaryClassName,
    stacked = false,
}: {
    date: DualDateValue;
    className?: string;
    secondaryClassName?: string;
    stacked?: boolean;
}) {
    return (
        <span
            className={cn(
                stacked
                    ? 'inline-flex flex-col'
                    : 'inline-flex flex-wrap items-baseline gap-x-2',
                className,
            )}
        >
            <time dateTime={date.gregorian} className="font-semibold">
                {date.ethiopian_label}
            </time>
            <span
                className={cn(
                    'text-[0.85em] text-muted-foreground tabular-nums',
                    secondaryClassName,
                )}
            >
                {date.gregorian}
            </span>
        </span>
    );
}
