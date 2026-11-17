import type { ReactNode } from 'react';
import { cx, filterFieldClassName } from '@/shell/Components/ui';

/**
 * A date picker as one field of a filter panel. EthiopianDateInput draws its
 * own label, so this gives it the panel's field width and caption (small,
 * muted) instead of wrapping it in FilterField's label.
 */
export function DateFilterField({ children }: { children: ReactNode }) {
    return (
        <div
            className={cx(
                filterFieldClassName(),
                '[&_label]:text-xs [&_label]:leading-normal [&_label]:text-(--app-muted-foreground) [&>div]:gap-1.5',
            )}
        >
            {children}
        </div>
    );
}
