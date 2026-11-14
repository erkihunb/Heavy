import type { ReactNode } from 'react';

type Tone = 'primary' | 'success' | 'warning' | 'neutral';

interface Props {
    label: string;
    value: string | number;
    icon?: ReactNode;
    tone?: Tone;
    hint?: ReactNode;
}

const toneChip: Record<Tone, string> = {
    primary: 'bg-brand-soft text-brand-strong',
    success: 'bg-success-soft text-success-strong',
    warning: 'bg-warning-soft text-warning-strong',
    neutral: 'bg-neutral-soft text-neutral-strong',
};

/**
 * Clean, gradient-free KPI tile for government-enterprise summary rows.
 * The value stays neutral; tone accents only the small icon chip.
 */
export default function StatCard({
    label,
    value,
    icon,
    tone = 'neutral',
    hint,
}: Props) {
    return (
        <div className="rounded-card border border-(--app-border) bg-(--app-surface) p-5">
            <div className="flex items-start justify-between gap-3">
                <p className="truncate text-sm font-medium text-(--app-muted-foreground)">
                    {label}
                </p>
                {icon && (
                    <div
                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-control ${toneChip[tone]}`}
                    >
                        {icon}
                    </div>
                )}
            </div>
            <p className="mt-2 text-3xl leading-tight font-bold text-(--app-foreground) tabular-nums">
                {value}
            </p>
            {hint && (
                <div className="mt-1 text-xs text-(--app-muted-foreground)">
                    {hint}
                </div>
            )}
        </div>
    );
}
