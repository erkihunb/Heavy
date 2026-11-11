import type {
    HTMLAttributes,
    ReactNode,
    TdHTMLAttributes,
    ThHTMLAttributes,
} from 'react';
import { cn } from '@/lib/utils';

/*
 * The one table style for lists (EUISIS): a hairline-bordered card, a muted
 * header row with small labels, row dividers and a hover wash. Pages use these
 * parts, never their own padding, colours or borders. Wide tables scroll
 * sideways inside their card on small screens.
 */

type Align = 'left' | 'center' | 'right';

const ALIGN: Record<Align, string> = {
    left: 'text-start',
    center: 'text-center',
    right: 'text-end',
};

/** A titled card around a table, with optional actions and footer. */
export function TableCard({
    title,
    titleId,
    description,
    actions,
    footer,
    className,
    children,
}: {
    title?: ReactNode;
    /** id of the title, for `aria-labelledby` on the table. */
    titleId?: string;
    description?: ReactNode;
    actions?: ReactNode;
    footer?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={title ? titleId : undefined}
            className={cn(
                'min-w-0 overflow-hidden rounded-card border border-border bg-card',
                className,
            )}
        >
            {(title || actions) && (
                <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-3">
                    <div className="min-w-0">
                        {title && (
                            <h2
                                id={titleId}
                                className="text-sm font-semibold text-foreground"
                            >
                                {title}
                            </h2>
                        )}
                        {description && (
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && (
                        <div className="flex flex-wrap items-center gap-2">
                            {actions}
                        </div>
                    )}
                </header>
            )}
            {children}
            {footer && (
                <footer className="border-t border-border px-4 py-3">
                    {footer}
                </footer>
            )}
        </section>
    );
}

export function Table({
    className,
    children,
    ...props
}: HTMLAttributes<HTMLTableElement>) {
    return (
        <div className="overflow-x-auto">
            <table
                className={cn(
                    'w-full text-start text-sm text-foreground',
                    className,
                )}
                {...props}
            >
                {children}
            </table>
        </div>
    );
}

export function THead({
    className,
    ...props
}: HTMLAttributes<HTMLTableSectionElement>) {
    return (
        <thead
            className={cn('border-b border-border bg-muted', className)}
            {...props}
        />
    );
}

export function TBody({
    className,
    ...props
}: HTMLAttributes<HTMLTableSectionElement>) {
    return (
        <tbody className={cn('divide-y divide-border', className)} {...props} />
    );
}

export function Tr({
    className,
    ...props
}: HTMLAttributes<HTMLTableRowElement>) {
    return (
        <tr
            className={cn('transition-colors hover:bg-muted/60', className)}
            {...props}
        />
    );
}

export function Th({
    align = 'left',
    className,
    ...props
}: ThHTMLAttributes<HTMLTableCellElement> & { align?: Align }) {
    return (
        <th
            scope="col"
            className={cn(
                'px-4 py-(--table-py) text-xs font-medium whitespace-nowrap text-muted-foreground',
                ALIGN[align],
                className,
            )}
            {...props}
        />
    );
}

export function Td({
    align = 'left',
    numeric = false,
    muted = false,
    className,
    ...props
}: TdHTMLAttributes<HTMLTableCellElement> & {
    align?: Align;
    /** Tabular figures, right-aligned unless `align` says otherwise. */
    numeric?: boolean;
    muted?: boolean;
}) {
    return (
        <td
            className={cn(
                'px-4 py-(--table-py) align-middle',
                ALIGN[numeric && align === 'left' ? 'right' : align],
                numeric && 'tabular-nums',
                muted && 'text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}

/** The single row of a table with nothing to show. */
export function EmptyRow({
    colSpan,
    children,
}: {
    colSpan: number;
    children: ReactNode;
}) {
    return (
        <tr>
            <td
                colSpan={colSpan}
                className="px-4 py-10 text-center text-sm text-muted-foreground"
            >
                {children}
            </td>
        </tr>
    );
}
