import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';

export type PageTab = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

/**
 * EUISIS underlined tabs under a page's heading band. Each tab is its own
 * page (a link), so the address and the browser history follow the tab.
 */
export function PageTabs({ tabs, label }: { tabs: PageTab[]; label: string }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <nav aria-label={label} className="-mt-6 mb-0">
            <ul className="relative isolate flex max-w-full gap-1 overflow-x-auto pt-1 before:absolute before:inset-y-0 before:left-1/2 before:-z-10 before:w-screen before:-translate-x-1/2 before:border-b before:border-border before:bg-muted">
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
                                        className="absolute inset-x-0 -bottom-px h-0.5 bg-primary"
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
