import type {
    NavGroup,
    NavItem,
    NavSection,
    Navigation,
} from '@/config/navigation';

/*
 * How the shell reads config/navigation.ts: which pages a person may see and
 * which one the current address belongs to. The source system matched Ziggy route names;
 * Heavy has no route names in the browser (Wayfinder gives addresses), so the
 * same "nearest module" rule is applied to paths: an exact match wins,
 * then an item that `owns` the path, then the item whose address is the
 * longest prefix of it (/staff/permits/12 → Review queue).
 *
 * Several items may open one page with different query parameters
 * (/staff/permits and /staff/permits?status=awaiting_payment). Among items on
 * the same path, the one whose query the address carries wins, else the
 * plain one, else the first, so exactly one item is ever highlighted.
 */

export type NavLocation = { group: NavGroup; item: NavItem };

/** The path of an address, without query, hash or trailing slash: "/staff/permits". */
export function pathOf(url: string): string {
    const path = url.split(/[?#]/)[0] ?? '';
    return path.length > 1 ? path.replace(/\/+$/, '') : path || '/';
}

/** Whether a person with these permissions sees the item. */
export function isPermitted(
    item: NavItem,
    can: (permission: string) => boolean,
): boolean {
    return (
        (!item.permission || can(item.permission)) &&
        (!item.anyPermission ||
            item.anyPermission.some((permission) => can(permission)))
    );
}

/** The menu without the pages a person may not see, and without groups and sections left empty. */
export function permittedSections(
    navigation: Navigation,
    can: (permission: string) => boolean,
): NavSection[] {
    return navigation.sections
        .map((section) => ({
            ...section,
            groups: section.groups
                .map((group) => ({
                    ...group,
                    items: group.items.filter((item) => isPermitted(item, can)),
                }))
                .filter((group) => group.items.length > 0),
        }))
        .filter((section) => section.groups.length > 0);
}

/** The query parameters of an address. */
function queryOf(url: string): URLSearchParams {
    return new URLSearchParams(url.split('#')[0]?.split('?')[1] ?? '');
}

/**
 * Of several items on one path: the one whose every query parameter the
 * address carries (the most specific first), else the plain one, else the first.
 */
function bestOnPath(
    candidates: NavItem[],
    current: URLSearchParams,
): NavItem | undefined {
    const size = (item: NavItem) => [...queryOf(item.href).keys()].length;
    const carried = [...candidates]
        .filter((item) => size(item) > 0)
        .sort((a, b) => size(b) - size(a))
        .find((item) =>
            [...queryOf(item.href).entries()].every(
                ([key, value]) => current.get(key) === value,
            ),
        );

    return (
        carried ?? candidates.find((item) => size(item) === 0) ?? candidates[0]
    );
}

/** The item the current address belongs to (by the rule above), or undefined. */
export function activeItem(
    items: NavItem[],
    currentUrl: string,
): NavItem | undefined {
    const current = pathOf(currentUrl);
    const params = queryOf(currentUrl);
    const exact = items.filter((item) => pathOf(item.href) === current);
    if (exact.length > 0) return bestOnPath(exact, params);
    const owner = items.find((item) =>
        item.owns?.some(
            (owned) => current === owned || current.startsWith(owned + '/'),
        ),
    );
    if (owner) return owner;
    const prefixed = items.filter(
        (item) =>
            pathOf(item.href) !== '/' &&
            current.startsWith(pathOf(item.href) + '/'),
    );
    const longest = Math.max(
        0,
        ...prefixed.map((item) => pathOf(item.href).length),
    );

    return bestOnPath(
        prefixed.filter((item) => pathOf(item.href).length === longest),
        params,
    );
}

/**
 * Where the current path sits in the menu, found with the same rule that
 * highlights the sidebar, so breadcrumbs and the selected item always agree.
 * Null for home and for paths the menu does not cover. Permissions are not
 * applied here; callers decide what to link.
 */
export function navLocation(
    navigation: Navigation,
    currentUrl: string,
): NavLocation | null {
    const groups = navigation.sections.flatMap((section) => section.groups);
    const match = activeItem(
        [navigation.home, ...groups.flatMap((group) => group.items)],
        currentUrl,
    );
    if (!match || match === navigation.home) return null;
    const group = groups.find((candidate) => candidate.items.includes(match));
    return group ? { group, item: match } : null;
}
