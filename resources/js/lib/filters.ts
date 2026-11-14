import { router } from '@inertiajs/react';

type FilterValue = string | number | boolean | null | undefined;

/**
 * The filters that are set: an empty text, null and undefined are left out,
 * so the address of a list carries only what narrows it.
 */
export function withoutEmpty<T extends Record<string, FilterValue>>(
    filters: T,
): Record<string, string | number | boolean> {
    return Object.fromEntries(
        Object.entries(filters).filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        ),
    ) as Record<string, string | number | boolean>;
}

/**
 * Show a list again with these filters. The page keeps what is typed in its
 * search box and where it is scrolled, and the visit replaces the current
 * history entry: Back leaves the list instead of undoing one keystroke.
 */
export function visitWithFilters(
    url: string,
    filters: Record<string, FilterValue>,
): void {
    router.get(url, withoutEmpty(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
