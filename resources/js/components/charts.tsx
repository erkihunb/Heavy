import { useId, useState } from 'react';
import type { PointerEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/*
 * Small, dependency-free charts for the dashboard. Every chart is drawn in the
 * theme's chart colour (--chart-1 follows the colour theme), keeps its grid
 * recessive and carries a screen-reader table with every value, so a tooltip
 * only ever repeats what is reachable without it.
 */

export type ChartPoint = {
    key: string;
    /** Full name of the period, used in the tooltip and the table. */
    label: string;
    /** Short name under the axis. */
    short: string;
    value: number;
    /** The value as people read it, e.g. "ETB 1,500.00". */
    display: string;
};

/** A panel with a title, a one-line description and room for a figure. */
export function ChartCard({
    title,
    description,
    aside,
    children,
    className,
}: {
    title: string;
    description?: string;
    aside?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    const id = useId();

    return (
        <section
            aria-labelledby={id}
            className={cn(
                'flex flex-col overflow-hidden rounded-panel border border-border bg-card',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3 border-b border-border px-4 py-3">
                <div className="min-w-0">
                    <h2 id={id} className="text-base font-semibold">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {aside && <div className="shrink-0 text-end">{aside}</div>}
            </div>
            <div className="flex-1 p-4">{children}</div>
        </section>
    );
}

/** Round the top of an axis up to 1, 2 or 5 times a power of ten. */
export function niceScale(
    max: number,
    ticks = 4,
): { top: number; step: number } {
    if (max <= 0) {
        return { top: ticks, step: 1 };
    }

    const raw = max / ticks;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const step =
        [1, 2, 5, 10].map((m) => m * magnitude).find((s) => s >= raw) ??
        10 * magnitude;
    const whole = Math.max(step, 1);

    return { top: whole * Math.ceil(max / whole), step: whole };
}

export function compactNumber(value: number, locale: string): string {
    return new Intl.NumberFormat(locale === 'am' ? 'am-ET' : 'en', {
        notation: value >= 10_000 ? 'compact' : 'standard',
        maximumFractionDigits: 1,
    }).format(value);
}

function Plot({
    top,
    step,
    format,
    children,
}: {
    top: number;
    step: number;
    format: (value: number) => string;
    children: ReactNode;
}) {
    const ticks: number[] = [];

    for (let tick = 0; tick <= top + step / 2; tick += step) {
        ticks.push(tick);
    }

    return (
        <div className="flex gap-2" aria-hidden="true">
            <div className="relative w-10 shrink-0 text-[11px] text-muted-foreground tabular-nums">
                {ticks.map((tick) => (
                    <span
                        key={tick}
                        className="absolute end-0 translate-y-1/2 leading-none"
                        style={{ bottom: `${(tick / top) * 100}%` }}
                    >
                        {format(tick)}
                    </span>
                ))}
            </div>
            <div className="relative h-44 flex-1">
                {ticks.map((tick) => (
                    <span
                        key={tick}
                        className={cn(
                            'absolute inset-x-0 h-px',
                            tick === 0 ? 'bg-border-strong' : 'bg-border',
                        )}
                        style={{ bottom: `${(tick / top) * 100}%` }}
                    />
                ))}
                {children}
            </div>
        </div>
    );
}

function Tooltip({
    point,
    left,
    bottom,
}: {
    point: ChartPoint;
    left: number;
    bottom: number;
}) {
    return (
        <div
            className={cn(
                'pointer-events-none absolute z-10 mb-2 rounded-md border border-border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-md',
                left > 70
                    ? '-translate-x-full'
                    : left < 30
                      ? ''
                      : '-translate-x-1/2',
            )}
            style={{ left: `${left}%`, bottom: `${bottom}%` }}
        >
            <p className="text-sm font-semibold tabular-nums">
                {point.display}
            </p>
            <p className="text-muted-foreground">{point.label}</p>
        </div>
    );
}

/** A table with every value, for screen readers. */
function DataTable({
    caption,
    data,
    period,
    value,
}: {
    caption: string;
    data: ChartPoint[];
    period: string;
    value: string;
}) {
    return (
        <table className="sr-only">
            <caption>{caption}</caption>
            <thead>
                <tr>
                    <th scope="col">{period}</th>
                    <th scope="col">{value}</th>
                </tr>
            </thead>
            <tbody>
                {data.map((point) => (
                    <tr key={point.key}>
                        <th scope="row">{point.label}</th>
                        <td>{point.display}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

type ChartProps = {
    data: ChartPoint[];
    /** Caption of the screen-reader table. */
    caption: string;
    /** Column headings of the screen-reader table. */
    periodLabel: string;
    valueLabel: string;
    /** Tick labels of the value axis. */
    formatTick: (value: number) => string;
};

/** Columns over time; the latest one carries its value on the cap. */
export function ColumnChart({
    data,
    caption,
    periodLabel,
    valueLabel,
    formatTick,
}: ChartProps) {
    const [hover, setHover] = useState<number | null>(null);
    const { top, step } = niceScale(Math.max(0, ...data.map((p) => p.value)));
    const columns = { gridTemplateColumns: `repeat(${data.length}, 1fr)` };
    const last = data.length - 1;

    return (
        <div>
            <Plot top={top} step={step} format={formatTick}>
                <div className="absolute inset-0 grid" style={columns}>
                    {data.map((point, index) => {
                        const height = (point.value / top) * 100;

                        return (
                            <div
                                key={point.key}
                                className="relative flex h-full items-end justify-center"
                                onPointerEnter={() => setHover(index)}
                                onPointerLeave={() => setHover(null)}
                            >
                                {index === last && hover !== index && (
                                    <span
                                        className="absolute mb-1 text-xs font-semibold whitespace-nowrap tabular-nums"
                                        style={{ bottom: `${height}%` }}
                                    >
                                        {formatTick(point.value)}
                                    </span>
                                )}
                                <span
                                    className={cn(
                                        'block w-full max-w-6 rounded-t-[4px] bg-chart-1 transition-opacity',
                                        hover !== null &&
                                            hover !== index &&
                                            'opacity-50',
                                    )}
                                    style={{
                                        height: `${height}%`,
                                        minHeight: point.value > 0 ? 2 : 0,
                                    }}
                                />
                            </div>
                        );
                    })}
                </div>
                {hover !== null && (
                    <Tooltip
                        point={data[hover]}
                        left={((hover + 0.5) / data.length) * 100}
                        bottom={Math.min((data[hover].value / top) * 100, 80)}
                    />
                )}
            </Plot>
            <div
                aria-hidden="true"
                className="ms-12 mt-2 grid text-center text-[11px] text-muted-foreground"
                style={columns}
            >
                {data.map((point) => (
                    <span key={point.key} className="truncate px-0.5">
                        {point.short}
                    </span>
                ))}
            </div>
            <DataTable
                caption={caption}
                data={data}
                period={periodLabel}
                value={valueLabel}
            />
        </div>
    );
}

/** A line with a light wash under it, a crosshair and the last value at its end. */
export function AreaChart({
    data,
    caption,
    periodLabel,
    valueLabel,
    formatTick,
}: ChartProps) {
    const [hover, setHover] = useState<number | null>(null);
    const { top, step } = niceScale(Math.max(0, ...data.map((p) => p.value)));
    const count = data.length;
    const x = (index: number) =>
        count === 1 ? 50 : (index / (count - 1)) * 100;
    const y = (value: number) => (value / top) * 100;
    const line = data
        .map(
            (p, index) =>
                `${index === 0 ? 'M' : 'L'}${x(index)},${100 - y(p.value)}`,
        )
        .join(' ');
    const area = `${line} L${x(count - 1)},100 L${x(0)},100 Z`;
    const labelEvery = Math.max(1, Math.ceil(count / 4));
    const last = data[count - 1];

    // The crosshair snaps to the nearest day, wherever the pointer is.
    const track = (event: PointerEvent<HTMLDivElement>) => {
        const box = event.currentTarget.getBoundingClientRect();
        const ratio = (event.clientX - box.left) / box.width;

        setHover(
            Math.min(count - 1, Math.max(0, Math.round(ratio * (count - 1)))),
        );
    };

    return (
        <div>
            <Plot top={top} step={step} format={formatTick}>
                <svg
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                    className="absolute inset-0 size-full overflow-visible"
                >
                    <path d={area} className="fill-chart-1 opacity-10" />
                    <path
                        d={line}
                        fill="none"
                        className="stroke-chart-1"
                        strokeWidth={2}
                        strokeLinejoin="round"
                        strokeLinecap="round"
                        vectorEffect="non-scaling-stroke"
                    />
                </svg>
                {hover !== null && (
                    <span
                        className="absolute inset-y-0 w-px bg-border-strong"
                        style={{ left: `${x(hover)}%` }}
                    />
                )}
                {(hover === null ? [count - 1] : [hover]).map((index) => (
                    <span
                        key={index}
                        className="absolute size-2.5 -translate-x-1/2 translate-y-1/2 rounded-full bg-chart-1 ring-2 ring-card"
                        style={{
                            left: `${x(index)}%`,
                            bottom: `${y(data[index].value)}%`,
                        }}
                    />
                ))}
                {hover === null && last && (
                    <span
                        className="absolute mb-2 -translate-x-full text-xs font-semibold whitespace-nowrap tabular-nums"
                        style={{ left: '100%', bottom: `${y(last.value)}%` }}
                    >
                        {formatTick(last.value)}
                    </span>
                )}
                {hover !== null && (
                    <Tooltip
                        point={data[hover]}
                        left={x(hover)}
                        bottom={Math.min(y(data[hover].value) + 4, 80)}
                    />
                )}
                <div
                    className="absolute inset-0"
                    onPointerMove={track}
                    onPointerLeave={() => setHover(null)}
                />
            </Plot>
            <div
                aria-hidden="true"
                className="relative ms-12 mt-2 h-4 text-[11px] text-muted-foreground"
            >
                {data.map((point, index) =>
                    (count - 1 - index) % labelEvery === 0 ? (
                        <span
                            key={point.key}
                            className={cn(
                                'absolute whitespace-nowrap',
                                index === count - 1
                                    ? '-translate-x-full'
                                    : index === 0
                                      ? ''
                                      : '-translate-x-1/2',
                            )}
                            style={{ left: `${x(index)}%` }}
                        >
                            {point.short}
                        </span>
                    ) : null,
                )}
            </div>
            <DataTable
                caption={caption}
                data={data}
                period={periodLabel}
                value={valueLabel}
            />
        </div>
    );
}

/** A 12-point trend under a KPI: history quiet, the latest point in the accent. */
export function Sparkline({ values }: { values: number[] }) {
    if (values.length < 2) {
        return null;
    }

    const max = Math.max(1, ...values);
    const x = (index: number) => (index / (values.length - 1)) * 100;
    const y = (value: number) => 22 - (value / max) * 20;
    const path = values
        .map((v, index) => `${index === 0 ? 'M' : 'L'}${x(index)},${y(v)}`)
        .join(' ');
    const lastIndex = values.length - 1;

    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 100 24"
            preserveAspectRatio="none"
            className="h-6 w-20 shrink-0 overflow-visible"
        >
            <path
                d={path}
                fill="none"
                className="stroke-muted-foreground/60"
                strokeWidth={1.5}
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
            />
            <circle
                cx={x(lastIndex)}
                cy={y(values[lastIndex])}
                r={2.5}
                className="fill-chart-1"
            />
        </svg>
    );
}

export type Segment = {
    key: string;
    label: string;
    value: number;
    /** Tailwind background class of the segment and its legend key. */
    color: string;
};

/**
 * One whole split into parts: a thin bar with a legend that gives every
 * part's name, number and share.
 */
export function SegmentedBar({
    parts,
    shareLabel,
}: {
    parts: Segment[];
    shareLabel: string;
}) {
    const total = parts.reduce((sum, part) => sum + part.value, 0);
    const shown = parts.filter((part) => part.value > 0);
    const share = (value: number) =>
        total === 0 ? 0 : Math.round((value / total) * 100);

    return (
        <div className="space-y-3">
            <div
                role="img"
                aria-label={shown
                    .map(
                        (part) =>
                            `${part.label}: ${part.value} (${share(part.value)}%)`,
                    )
                    .join(', ')}
                className="flex h-2 gap-[2px] overflow-hidden rounded-full bg-muted"
            >
                {shown.map((part) => (
                    <span
                        key={part.key}
                        title={`${part.label}: ${part.value} (${share(part.value)}%)`}
                        className={cn('h-full rounded-full', part.color)}
                        style={{ width: `${(part.value / total) * 100}%` }}
                    />
                ))}
            </div>
            <ul className="grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                {parts.map((part) => (
                    <li
                        key={part.key}
                        className="flex items-center justify-between gap-3 text-sm"
                    >
                        <span className="flex min-w-0 items-center gap-2">
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'size-2.5 shrink-0 rounded-xs',
                                    part.color,
                                )}
                            />
                            <span className="truncate">{part.label}</span>
                        </span>
                        <span className="font-semibold tabular-nums">
                            {part.value}
                            <span
                                className="ms-1.5 text-xs font-normal text-muted-foreground"
                                aria-label={shareLabel}
                            >
                                {share(part.value)}%
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

const METER_TONE = {
    primary: { fill: 'bg-chart-1', track: 'bg-chart-1/15' },
    success: {
        fill: 'bg-emerald-600 dark:bg-emerald-500',
        track: 'bg-emerald-600/15',
    },
    warning: {
        fill: 'bg-amber-500',
        track: 'bg-amber-500/15',
    },
    critical: { fill: 'bg-red-600', track: 'bg-red-600/15' },
} as const;

/** How far something has got, as a share of a whole. */
export function Meter({
    label,
    percent,
    value,
    caption,
    tone = 'primary',
    size = 'md',
}: {
    label: string;
    percent: number;
    /** What is printed at the end of the label row; the percentage by default. */
    value?: string;
    caption?: string;
    tone?: keyof typeof METER_TONE;
    size?: 'sm' | 'md';
}) {
    const clamped = Math.min(100, Math.max(0, percent));

    return (
        <div>
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-sm text-muted-foreground">{label}</span>
                <span
                    className={cn(
                        'font-semibold',
                        size === 'md' ? 'text-2xl' : 'text-sm tabular-nums',
                    )}
                >
                    {value ?? `${Math.round(clamped)}%`}
                </span>
            </div>
            <div
                role="progressbar"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={Math.round(clamped)}
                className={cn(
                    'mt-2 overflow-hidden rounded-full',
                    size === 'md' ? 'h-2' : 'h-1.5',
                    METER_TONE[tone].track,
                )}
            >
                <span
                    className={cn(
                        'block h-full rounded-full',
                        METER_TONE[tone].fill,
                    )}
                    style={{ width: `${clamped}%` }}
                />
            </div>
            {caption && (
                <p className="mt-1.5 text-xs text-muted-foreground">
                    {caption}
                </p>
            )}
        </div>
    );
}
