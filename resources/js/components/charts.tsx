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
                'flex flex-col overflow-hidden rounded-card border border-border bg-card shadow-md',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3 px-5 pt-5">
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
            <div className="flex-1 p-5 pt-4">{children}</div>
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

/**
 * A curve through every point that never overshoots them (monotone cubic,
 * Fritsch-Carlson): a flat run stays flat and every peak is a real value, so
 * the line cannot dip under zero between two points.
 */
export function smoothLine(points: { x: number; y: number }[]): string {
    const count = points.length;

    if (count < 3) {
        return points
            .map((p, index) => `${index === 0 ? 'M' : 'L'}${p.x},${p.y}`)
            .join(' ');
    }

    const width: number[] = [];
    const slope: number[] = [];

    for (let i = 0; i < count - 1; i++) {
        width[i] = points[i + 1].x - points[i].x;
        slope[i] = (points[i + 1].y - points[i].y) / width[i];
    }

    const tangent: number[] = [slope[0]];

    for (let i = 1; i < count - 1; i++) {
        tangent[i] =
            slope[i - 1] * slope[i] <= 0 ? 0 : (slope[i - 1] + slope[i]) / 2;
    }

    tangent[count - 1] = slope[count - 2];

    for (let i = 0; i < count - 1; i++) {
        if (slope[i] === 0) {
            tangent[i] = 0;
            tangent[i + 1] = 0;
            continue;
        }

        const a = tangent[i] / slope[i];
        const b = tangent[i + 1] / slope[i];
        const length = Math.hypot(a, b);

        if (length > 3) {
            tangent[i] = (3 / length) * a * slope[i];
            tangent[i + 1] = (3 / length) * b * slope[i];
        }
    }

    const round = (value: number) => Math.round(value * 100) / 100;
    let path = `M${round(points[0].x)},${round(points[0].y)}`;

    for (let i = 0; i < count - 1; i++) {
        const third = width[i] / 3;
        const from = points[i];
        const to = points[i + 1];

        path += ` C${round(from.x + third)},${round(from.y + tangent[i] * third)} ${round(to.x - third)},${round(to.y - tangent[i + 1] * third)} ${round(to.x)},${round(to.y)}`;
    }

    return path;
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
            <div className="relative h-52 flex-1">
                {ticks.map((tick) => (
                    <span
                        key={tick}
                        className={cn(
                            // Dashed guides stay behind the data; only the baseline is a solid rule.
                            'absolute inset-x-0 border-t',
                            tick === 0
                                ? 'border-border-strong'
                                : 'border-dashed border-border',
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
                                        'chart-grow block w-full max-w-9 rounded-t-md bg-chart-1 transition-opacity',
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

/** A smooth line over a wash that fades out, with a crosshair and the last value at its end. */
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
    const gradient = useId();
    const line = smoothLine(
        data.map((p, index) => ({ x: x(index), y: 100 - y(p.value) })),
    );
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
                    <defs>
                        <linearGradient
                            id={gradient}
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="var(--chart-1)"
                                stopOpacity={0.28}
                            />
                            <stop
                                offset="100%"
                                stopColor="var(--chart-1)"
                                stopOpacity={0}
                            />
                        </linearGradient>
                    </defs>
                    <path
                        d={area}
                        fill={`url(#${gradient})`}
                        className="chart-fade"
                    />
                    <path
                        d={line}
                        fill="none"
                        className="chart-fade stroke-chart-1"
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

/** A 12-point trend under a KPI: a smooth line over a fading wash, the latest point marked. */
export function Sparkline({ values }: { values: number[] }) {
    const gradient = useId();

    if (values.length < 2) {
        return null;
    }

    const max = Math.max(1, ...values);
    const x = (index: number) => (index / (values.length - 1)) * 100;
    const y = (value: number) => 22 - (value / max) * 20;
    const path = smoothLine(
        values.map((v, index) => ({ x: x(index), y: y(v) })),
    );
    const lastIndex = values.length - 1;

    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 100 24"
            preserveAspectRatio="none"
            className="h-6 w-20 shrink-0 overflow-visible"
        >
            <defs>
                <linearGradient id={gradient} x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stopColor="var(--chart-1)"
                        stopOpacity={0.25}
                    />
                    <stop
                        offset="100%"
                        stopColor="var(--chart-1)"
                        stopOpacity={0}
                    />
                </linearGradient>
            </defs>
            <path d={`${path} L100,24 L0,24 Z`} fill={`url(#${gradient})`} />
            <path
                d={path}
                fill="none"
                className="stroke-chart-1"
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
            <Legend
                parts={parts}
                total={total}
                shareLabel={shareLabel}
                className="sm:grid-cols-2"
            />
        </div>
    );
}

/** Every part's name, number and share, keyed by the colour it is drawn in. */
function Legend({
    parts,
    total,
    shareLabel,
    className,
}: {
    parts: Segment[];
    total: number;
    shareLabel: string;
    className?: string;
}) {
    const share = (value: number) =>
        total === 0 ? 0 : Math.round((value / total) * 100);

    return (
        <ul className={cn('grid gap-x-6 gap-y-1.5', className)}>
            {parts.map((part) => (
                <li
                    key={part.key}
                    className="flex items-center justify-between gap-3 text-sm"
                >
                    <span className="flex min-w-0 items-center gap-2">
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-2.5 shrink-0 rounded-full',
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
    );
}

/**
 * One whole split into parts as a ring, with the total in its centre and the
 * same legend as the bar. Each part is a disc in its own colour masked to its
 * slice, and the hole is one more mask, so a part's colour stays a class.
 */
export function DonutChart({
    parts,
    shareLabel,
    totalLabel,
}: {
    parts: Segment[];
    shareLabel: string;
    /** What the number in the centre counts, e.g. "Permits". */
    totalLabel: string;
}) {
    const total = parts.reduce((sum, part) => sum + part.value, 0);
    const shown = parts.filter((part) => part.value > 0);
    const share = (value: number) =>
        total === 0 ? 0 : Math.round((value / total) * 100);
    const hole =
        'radial-gradient(farthest-side, transparent calc(100% - 16px), #000 calc(100% - 15px))';
    // A hairline gap shows between two slices, never around a lone one.
    const gap = shown.length > 1 ? 1.5 : 0;
    const angle = (part: Segment) => (part.value / total) * 360;
    const slices = shown.map((part, index) => {
        const start = shown
            .slice(0, index)
            .reduce((sum, earlier) => sum + angle(earlier), 0);
        const mask = `conic-gradient(from ${start}deg, #000 ${Math.max(angle(part) - gap, 0.5)}deg, transparent 0)`;

        return { part, mask };
    });

    return (
        <div className="flex flex-col items-center gap-5 sm:flex-row">
            <div className="relative size-36 shrink-0">
                <div
                    role="img"
                    aria-label={shown
                        .map(
                            (part) =>
                                `${part.label}: ${part.value} (${share(part.value)}%)`,
                        )
                        .join(', ')}
                    className="chart-fade absolute inset-0 rounded-full bg-muted"
                    style={{ maskImage: hole, WebkitMaskImage: hole }}
                >
                    {slices.map(({ part, mask }) => (
                        <span
                            key={part.key}
                            title={`${part.label}: ${part.value} (${share(part.value)}%)`}
                            className={cn(
                                'absolute inset-0 rounded-full',
                                part.color,
                            )}
                            style={{ maskImage: mask, WebkitMaskImage: mask }}
                        />
                    ))}
                </div>
                <div className="absolute inset-0 grid place-content-center text-center">
                    <p className="text-2xl leading-tight font-bold tabular-nums">
                        {total}
                    </p>
                    <p className="max-w-24 text-xs text-muted-foreground">
                        {totalLabel}
                    </p>
                </div>
            </div>
            <Legend
                parts={parts}
                total={total}
                shareLabel={shareLabel}
                className="w-full min-w-0 flex-1"
            />
        </div>
    );
}

const METER_TONE = {
    primary: { fill: 'bg-chart-1', track: 'bg-chart-1/15' },
    success: {
        fill: 'bg-success',
        track: 'bg-success/15',
    },
    warning: {
        fill: 'bg-warning',
        track: 'bg-warning/15',
    },
    critical: { fill: 'bg-destructive', track: 'bg-destructive/15' },
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
                        'chart-fill block h-full rounded-full',
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
