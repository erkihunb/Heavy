import { router } from '@inertiajs/react';
import {
    createColumnHelper as createFeatureColumnHelper,
    flexRender,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';
import type {
    CellData,
    ColumnDef as FeatureColumnDef,
    RowData,
} from '@tanstack/react-table';
import type { ReactNode } from 'react';
import EmptyState from '@/shell/Components/EmptyState';
import { AppTableSkeleton } from '@/shell/Components/ui/AppSkeleton';
import ListPagination from '@/shell/Components/ui/ListPagination';
import type { LaravelPageMeta } from '@/shell/Components/ui/ListPagination';
import { useLocale } from '@/shell/hooks/useLocale';
import {
    TableTools,
    cx,
    tableBodyClassName,
    tableCellClassName,
    tableClassName,
    tableContainerClassName,
    tableHeadClassName,
    tableHeaderCellClassName,
    tableRowClassName,
} from '@app/ui';

/**
 * TanStack Table 9 types every column by the table's feature set. These tables
 * use none (rows are drawn as given; pagination is Laravel's), so one empty set
 * is bound here and pages keep writing ColumnDef<Row> / createColumnHelper<Row>().
 */
const features = tableFeatures({});
type Features = typeof features;

export type ColumnDef<
    TData extends RowData,
    TValue extends CellData = CellData,
> = FeatureColumnDef<Features, TData, TValue>;
export const createColumnHelper = <TData extends RowData>() =>
    createFeatureColumnHelper<Features, TData>();

export type AppDataTableMeta = LaravelPageMeta;

interface AppDataTableProps<TData extends RowData> {
    /** Name under which TableTools remembers the viewer's columns and density; defaults to `href`. */
    id?: string;
    /** Row data. */
    data: TData[];
    /** TanStack column definitions. */
    // Each column has its own value type; `any` is how a mixed column list is typed in TanStack Table.
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    columns: ColumnDef<TData, any>[];
    /** Pagination metadata from Laravel paginator. */
    meta?: AppDataTableMeta;
    /** Active filter values — spread into pagination router.get calls. */
    filters?: Record<string, string | number | undefined | null>;
    /**
     * The list's address for page navigation, from a Wayfinder route
     * (`permits().url`). Without it, the pagination bar re-requests the
     * current address with the new page, keeping its query string.
     */
    href?: string;
    /** Show skeleton rows while loading. */
    loading?: boolean;
    /** Rows per skeleton when loading. Defaults to per_page or 8. */
    skeletonRows?: number;
    /** Text shown in the empty state. */
    emptyTitle?: string;
    emptyDescription?: string;
    emptyAction?: ReactNode;
    /** Extra classes on the outer wrapper. */
    className?: string;
}

/**
 * Server-side data table built on TanStack Table v9, drawn with the shared
 * table classes: header, rows, loading skeleton, empty state and the
 * pagination bar as the card's footer.
 *
 * @example
 * ```tsx
 * <AppDataTable data={permits.data} columns={columns} meta={permits.meta} filters={filters} href={permitsIndex().url} />
 * ```
 */
export default function AppDataTable<TData extends RowData>({
    id,
    data,
    columns,
    meta,
    filters = {},
    href,
    loading = false,
    skeletonRows,
    emptyTitle,
    emptyDescription,
    emptyAction,
    className = '',
}: AppDataTableProps<TData>) {
    const { t } = useLocale();

    const table = useTable({ features, data, columns });

    function goToPage(page: number) {
        if (!href) return;
        const clean: Record<string, string> = {};
        Object.entries(filters).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') clean[k] = String(v);
        });
        router.get(
            href,
            { ...clean, page },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <TableTools
            id={id ?? href ?? 'data-table'}
            className={cx(tableContainerClassName, className)}
        >
            {!loading && data.length === 0 ? (
                <EmptyState
                    title={emptyTitle ?? t('shell.common.no_results')}
                    description={emptyDescription}
                    action={emptyAction}
                />
            ) : (
                <div className="relative overflow-x-auto">
                    <table className={tableClassName}>
                        <thead className={tableHeadClassName}>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <tr key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <th
                                            key={header.id}
                                            colSpan={header.colSpan}
                                            scope="col"
                                            className={cx(
                                                tableHeaderCellClassName,
                                                'text-left',
                                            )}
                                            style={
                                                header.column.columnDef.meta as
                                                    | React.CSSProperties
                                                    | undefined
                                            }
                                        >
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                        <tbody className={tableBodyClassName}>
                            {loading ? (
                                <AppTableSkeleton
                                    rows={skeletonRows ?? meta?.per_page ?? 8}
                                    cols={columns.length}
                                />
                            ) : (
                                table.getRowModel().rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={tableRowClassName}
                                    >
                                        {row.getAllCells().map((cell) => (
                                            <td
                                                key={cell.id}
                                                className={tableCellClassName}
                                                style={
                                                    cell.column.columnDef
                                                        .meta as
                                                        | React.CSSProperties
                                                        | undefined
                                                }
                                            >
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            <ListPagination
                meta={meta}
                onPageChange={href ? goToPage : undefined}
                disabled={loading}
            />
        </TableTools>
    );
}

/** The pagination bar on its own (kept for existing imports; prefer ListPagination). */
export function AppDataTablePagination({
    meta,
    onPageChange,
}: {
    meta: AppDataTableMeta;
    onPageChange: (page: number) => void;
}) {
    return <ListPagination meta={meta} onPageChange={onPageChange} bare />;
}
