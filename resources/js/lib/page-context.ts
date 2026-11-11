import { createContext, useContext } from 'react';
import type { BreadcrumbItem } from '@/types';

/**
 * The breadcrumbs of the current page, handed from the layout to the page
 * heading band (PageHeader), where they sit above the title as in EUISIS.
 */
export const BreadcrumbsContext = createContext<BreadcrumbItem[]>([]);

export function usePageBreadcrumbs(): BreadcrumbItem[] {
    return useContext(BreadcrumbsContext);
}
