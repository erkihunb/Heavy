import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import type { Paginated } from '@/types';

/**
 * Previous / next navigation for a server-paginated list. Renders nothing
 * when everything fits on one page.
 */
export function Pagination({
    links,
    meta,
}: Pick<Paginated<unknown>, 'links' | 'meta'>) {
    const { t } = useTranslation();

    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label={t('common.pagination.label')}
            className="flex items-center justify-between gap-3"
        >
            {links.prev ? (
                <Button asChild variant="outline">
                    <Link href={links.prev} preserveScroll>
                        <ChevronLeft aria-hidden="true" />
                        {t('common.pagination.previous')}
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    <ChevronLeft aria-hidden="true" />
                    {t('common.pagination.previous')}
                </Button>
            )}

            <p className="text-sm text-muted-foreground tabular-nums">
                {t('common.pagination.page_of', {
                    current: meta.current_page,
                    last: meta.last_page,
                })}
            </p>

            {links.next ? (
                <Button asChild variant="outline">
                    <Link href={links.next} preserveScroll>
                        {t('common.pagination.next')}
                        <ChevronRight aria-hidden="true" />
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    {t('common.pagination.next')}
                    <ChevronRight aria-hidden="true" />
                </Button>
            )}
        </nav>
    );
}
