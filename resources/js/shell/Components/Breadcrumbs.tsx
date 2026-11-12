import { Link, usePage } from '@inertiajs/react';
import { navigation } from '@/config/navigation';
import { ChevronRight } from '@/shell/Components/NavIcons';
import { useCan } from '@/shell/hooks/useCan';
import { useLocale } from '@/shell/hooks/useLocale';
import { isPermitted, navLocation, pathOf } from '@/shell/lib/navigation';

/** Labels for the last segment of a sub-page under a menu item, keyed by URL segment. */
const SEGMENT_LABEL_KEYS: Record<string, string> = {
    create: 'shell.breadcrumbs.create',
    edit: 'shell.breadcrumbs.edit',
};

const UUID_RE =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export type Crumb = { label: string; href?: string };

function isIdSegment(segment: string) {
    return /^\d+$/.test(segment) || UUID_RE.test(segment);
}

/**
 * Breadcrumb trail: home › group › page › sub-page, from the menu
 * (config/navigation.ts) when the page is in it. A page can pass its own
 * trail instead (`items`, after home), for pages the menu does not cover.
 */
export default function Breadcrumbs({
    className = '',
    items,
}: {
    className?: string;
    items?: Crumb[];
}) {
    const { url } = usePage();
    const { t } = useLocale();
    const { can } = useCan();
    const pathname = pathOf(url);
    const home: Crumb = {
        label: t(navigation.home.labelKey),
        href: navigation.home.href,
    };

    let crumbs: Crumb[];
    const location = navLocation(navigation, url);
    if (items && items.length > 0) {
        crumbs = [home, ...items];
    } else if (location) {
        const { group, item } = location;
        const isCurrent = pathname === pathOf(item.href);
        crumbs = [
            home,
            // Groups organise the menu; they are not pages, so they are never links.
            { label: t(group.labelKey) },
            {
                label: t(item.labelKey),
                href:
                    isCurrent || !isPermitted(item, can)
                        ? undefined
                        : item.href,
            },
        ];
        if (!isCurrent) {
            // A sub-page of the module: "Edit" or "Create" by its last segment, "Details" for a record.
            const rest = pathname
                .slice(pathOf(item.href).length)
                .split('/')
                .filter(Boolean);
            const last = rest[rest.length - 1] ?? '';
            const key =
                SEGMENT_LABEL_KEYS[last] ??
                (rest.some(isIdSegment) ? 'shell.breadcrumbs.details' : '');
            if (key) crumbs.push({ label: t(key) });
        }
    } else {
        return null;
    }
    if (crumbs.length < 2) return null;

    // Phones show only the way back ("‹ Parent"); the full trail is still read out (sr-only) and shown from `sm` up.
    const parent = crumbs[crumbs.length - 2];
    const parentClass =
        'inline-flex max-w-full items-center gap-1 rounded-sm font-medium text-(--app-foreground) sm:hidden';

    return (
        <nav
            aria-label={t('shell.breadcrumbs.label')}
            className={
                'min-w-0 overflow-x-auto text-xs text-(--app-muted-foreground) ' +
                className
            }
        >
            {parent.href ? (
                <Link
                    href={parent.href}
                    className={
                        parentClass +
                        ' transition-colors hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-hidden'
                    }
                    aria-hidden="true"
                    tabIndex={-1}
                >
                    <ChevronRight
                        className="h-3 w-3 shrink-0 rotate-180 opacity-60 rtl:rotate-0"
                        aria-hidden="true"
                    />
                    <span className="truncate">{parent.label}</span>
                </Link>
            ) : (
                <span className={parentClass} aria-hidden="true">
                    <ChevronRight
                        className="h-3 w-3 shrink-0 rotate-180 opacity-60 rtl:rotate-0"
                        aria-hidden="true"
                    />
                    <span className="truncate">{parent.label}</span>
                </span>
            )}
            <ol className="sr-only flex min-w-max items-center gap-1.5 whitespace-nowrap sm:not-sr-only sm:flex">
                {crumbs.map((crumb, index) => {
                    const last = index === crumbs.length - 1;
                    return (
                        <li
                            key={index}
                            className="flex min-w-0 items-center gap-1.5"
                        >
                            {index > 0 && (
                                <ChevronRight
                                    className="h-3 w-3 shrink-0 opacity-60"
                                    aria-hidden="true"
                                />
                            )}
                            {last ? (
                                <span
                                    aria-current="page"
                                    className="max-w-64 truncate font-medium text-(--app-foreground)"
                                >
                                    {crumb.label}
                                </span>
                            ) : crumb.href ? (
                                <Link
                                    href={crumb.href}
                                    className="max-w-48 truncate rounded-sm transition-colors hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-hidden"
                                >
                                    {crumb.label}
                                </Link>
                            ) : (
                                <span className="max-w-48 truncate">
                                    {crumb.label}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
