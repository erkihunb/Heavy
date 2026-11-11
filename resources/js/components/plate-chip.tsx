import { cn } from '@/lib/utils';

/**
 * A vehicle plate number, styled like the plate itself so it is recognisable
 * at a glance in lists and on the permit. Never translated or re-flowed.
 */
export function PlateChip({
    plate,
    className,
}: {
    plate: string;
    className?: string;
}) {
    return (
        <span
            translate="no"
            className={cn(
                'inline-flex items-center rounded-lg border-2 border-foreground bg-card px-3 py-1 text-base font-extrabold tracking-[0.08em] whitespace-nowrap text-foreground tabular-nums',
                className,
            )}
        >
            {plate}
        </span>
    );
}
