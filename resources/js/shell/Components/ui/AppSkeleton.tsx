interface SkeletonProps {
    className?: string;
}

/** Single animated pulse block — compose to build any loading shape. */
export function AppSkeleton({ className = '' }: SkeletonProps) {
    return (
        <div
            aria-hidden="true"
            className={`animate-pulse rounded-sm bg-(--app-surface-muted) ${className}`}
        />
    );
}

/** Full-width skeleton row for use inside a <tbody>. */
export function AppTableRowSkeleton({ cols = 5 }: { cols?: number }) {
    return (
        <tr aria-hidden="true">
            {Array.from({ length: cols }).map((_, j) => (
                <td key={j} className="px-4 py-3">
                    <AppSkeleton className="h-4 w-full" />
                </td>
            ))}
        </tr>
    );
}

/** Repeating skeleton rows — drop directly into a <tbody>. */
export function AppTableSkeleton({
    rows = 6,
    cols = 5,
}: {
    rows?: number;
    cols?: number;
}) {
    return (
        <>
            {Array.from({ length: rows }).map((_, i) => (
                <AppTableRowSkeleton key={i} cols={cols} />
            ))}
        </>
    );
}

/** Skeleton card for metric/stat grids. */
export function AppMetricCardSkeleton() {
    return (
        <div
            aria-hidden="true"
            className="overflow-hidden rounded-card border border-(--app-border) bg-(--app-surface) p-5"
        >
            <AppSkeleton className="mb-3 h-3 w-24" />
            <AppSkeleton className="h-7 w-16" />
        </div>
    );
}

/** Skeleton page header (title + action button). */
export function AppPageHeaderSkeleton() {
    return (
        <div aria-hidden="true" className="flex items-start justify-between">
            <AppSkeleton className="h-7 w-40" />
            <AppSkeleton className="h-9 w-28 rounded-lg" />
        </div>
    );
}

export default AppSkeleton;
