import type { ReactNode } from 'react';
import {
    TrendingUpIcon as TrendingUp,
    TrendingDownIcon as TrendingDown,
    MinusIcon as Minus,
} from '@/shell/Components/Icons';

type Variant =
    'primary' | 'success' | 'warning' | 'danger' | 'accent' | 'neutral';
type Trend = 'up' | 'down' | 'neutral';

interface AppMetricCardProps {
    label: string;
    value: string | number;
    detail?: ReactNode;
    icon?: ReactNode;
    variant?: Variant;
    trend?: Trend;
    trendLabel?: string;
    loading?: boolean;
}

const variantStyles: Record<Variant, { icon: string; value: string }> = {
    primary: {
        icon: 'bg-brand-soft text-brand-strong',
        value: 'text-(--app-foreground)',
    },
    success: {
        icon: 'bg-success-soft text-success-strong',
        value: 'text-(--app-foreground)',
    },
    warning: {
        icon: 'bg-warning-soft text-warning-strong',
        value: 'text-(--app-foreground)',
    },
    danger: {
        icon: 'bg-danger-soft text-danger-strong',
        value: 'text-danger-strong',
    },
    accent: {
        icon: 'bg-warning-soft text-brand-accent',
        value: 'text-(--app-foreground)',
    },
    neutral: {
        icon: 'bg-neutral-soft text-neutral-strong',
        value: 'text-(--app-foreground)',
    },
};

const trendStyles: Record<Trend, { cls: string; Icon: typeof TrendingUp }> = {
    up: { cls: 'text-success-strong', Icon: TrendingUp },
    down: { cls: 'text-danger-strong', Icon: TrendingDown },
    neutral: { cls: 'text-(--app-muted-foreground)', Icon: Minus },
};

export default function AppMetricCard({
    label,
    value,
    detail,
    icon,
    variant = 'primary',
    trend,
    trendLabel,
    loading = false,
}: AppMetricCardProps) {
    const styles = variantStyles[variant];

    if (loading) {
        return (
            <div className="overflow-hidden rounded-card border border-(--app-border) bg-(--app-surface) p-5">
                <div className="animate-pulse space-y-3">
                    <div className="h-3 w-24 rounded-sm bg-(--app-surface-muted)" />
                    <div className="h-7 w-16 rounded-sm bg-(--app-surface-muted)" />
                </div>
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-card border border-(--app-border) bg-(--app-surface) p-5 transition-shadow hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-(--app-muted-foreground)">
                    {label}
                </p>
                {icon && (
                    <div
                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-control ${styles.icon}`}
                    >
                        {icon}
                    </div>
                )}
            </div>

            <p
                className={`mt-2 text-3xl leading-tight font-bold tabular-nums ${styles.value}`}
            >
                {value}
            </p>

            {(trend || detail) && (
                <div className="mt-2 flex items-center gap-2">
                    {trend &&
                        (() => {
                            const TrendIcon = trendStyles[trend].Icon;
                            return (
                                <span
                                    className={`inline-flex items-center gap-1 text-xs font-medium ${trendStyles[trend].cls}`}
                                >
                                    <TrendIcon
                                        className="h-3 w-3"
                                        aria-hidden="true"
                                    />
                                    {trendLabel}
                                </span>
                            );
                        })()}
                    {detail && (
                        <span className="text-xs text-(--app-muted-foreground)">
                            {detail}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
