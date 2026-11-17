import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';

export type PageTab = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

/**
 * Underlined tabs under a page's heading. Each tab is its own
 * page (a link), so the address and the browser history follow the tab.
 */
export function PageTabs({ tabs, label }: { tabs: PageTab[]; label: string }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <nav aria-label={label} className="border-b border-border">
            {/* The row scrolls sideways on a narrow screen; the active tab's rule sits on the nav's border. */}
            <ul className="-mb-px flex max-w-full gap-1 overflow-x-auto">
                {tabs.map((tab) => {
                    const active = isCurrentOrParentUrl(tab.href);

                    return (
                        <li key={toUrl(tab.href)} className="shrink-0">
                            <Link
                                href={tab.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'relative flex min-h-11 items-center px-3 py-2 text-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none focus-visible:ring-inset',
                                    active
                                        ? 'font-semibold text-primary'
                                        : 'font-medium text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                                {active && (
                                    <span
                                        aria-hidden="true"
                                        className="absolute inset-x-0 bottom-0 h-0.5 bg-primary"
                                    />
                                )}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
