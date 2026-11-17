import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { navigation } from '@/config/navigation';
import type { NavGroup, NavItem, NavSection } from '@/config/navigation';
import { useHydrated } from '@/hooks/use-hydrated';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import ApplicationLogo from '@/shell/Components/ApplicationLogo';
import {
    ChevronDown,
    LogOut,
    SearchIcon,
    X,
} from '@/shell/Components/NavIcons';
import UserAvatar from '@/shell/Components/UserAvatar';
import { useBranding } from '@/shell/hooks/useBranding';
import { useCan } from '@/shell/hooks/useCan';
import { useLocale } from '@/shell/hooks/useLocale';
import { useSystemSettings } from '@/shell/hooks/useSystemSettings';
import {
    activeItem,
    isPermitted,
    permittedSections,
} from '@/shell/lib/navigation';

const SIDEBAR_GROUPS_STORAGE_KEY = 'heavy-sidebar-open-groups';

interface Props {
    onClose?: () => void;
    collapsed?: boolean;
    onToggleCollapse?: () => void;
}

const focusRing =
    'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-(--sidebar-accent)';
const hoverSurface = 'hover:bg-(--sidebar-hover)';
const selectedSurface = 'bg-(--sidebar-accent-wash) text-(--sidebar-accent)';

/**
 * The sidebar: brand block, menu filter, sections of collapsible
 * groups (flyouts on the collapsed rail) and the collapse control. The menu
 * itself is config/navigation.ts, filtered by the person's permissions.
 */
export default function AppSidebar({
    onClose,
    collapsed = false,
    onToggleCollapse,
}: Props) {
    const { can } = useCan();
    const { t } = useLocale();
    const { getString } = useSystemSettings();
    const { url: pageUrl } = usePage();
    const instanceId = useId();
    const searchRef = useRef<HTMLInputElement>(null);
    const navRef = useRef<HTMLElement>(null);
    const focusSearchAfterExpand = useRef(false);
    const [query, setQuery] = useState('');
    const normalizedQuery = query.trim().toLocaleLowerCase();

    const { brandName: appName, shortName } = useBranding();
    const orgName = getString('general.organization_name');
    const environmentLabel = getString('general.system_environment_label');
    const logoCentered =
        getString('appearance.logo_position', 'start') === 'center';
    // A centred logo stacks the names under it, so that block takes the height it needs.
    const stackedBrand = logoCentered && !collapsed;
    const user = usePage().props.auth.user;
    const displayName = user?.name ?? t('shell.common.user');
    const firstRole = user?.roles?.[0];
    const role = firstRole ? t(`roles.${firstRole}.name`) : undefined;

    const homeNav = navigation.home;
    // The dashboard link, for those who hold its permission.
    const showHome = isPermitted(homeNav, can);
    const sections: NavSection[] = permittedSections(navigation, can);
    const allGroups = sections.flatMap((section) => section.groups);
    const currentUrl = pageUrl;
    const currentItem = activeItem(
        [homeNav, ...allGroups.flatMap((group) => group.items)],
        currentUrl,
    )?.href;
    const activeGroupKeys = allGroups
        .filter((group) =>
            group.items.some((item) => item.href === currentItem),
        )
        .map((group) => group.key);
    const activeGroupSignature = activeGroupKeys.join(',');

    const withActiveOpen = (
        groups: Record<string, boolean>,
    ): Record<string, boolean> => {
        const next = { ...groups };
        for (const key of activeGroupKeys) next[key] = true;
        return next;
    };

    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(
        () => {
            try {
                const parsed: unknown = JSON.parse(
                    window.localStorage.getItem(SIDEBAR_GROUPS_STORAGE_KEY) ??
                        '{}',
                );
                if (
                    parsed &&
                    typeof parsed === 'object' &&
                    !Array.isArray(parsed)
                ) {
                    return withActiveOpen(
                        Object.fromEntries(
                            Object.entries(parsed).filter(
                                ([, value]) => typeof value === 'boolean',
                            ),
                        ),
                    );
                }
            } catch {
                /* Storage is optional. */
            }
            return withActiveOpen({});
        },
    );

    // The groups a person opened are kept in localStorage, which a page drawn on
    // the server cannot read: until the browser has taken over, only the current
    // page's group is open, as the server drew it.
    const hydrated = useHydrated();
    const shownGroups = hydrated ? openGroups : withActiveOpen({});

    // After each visit the current page's group opens and the filter clears. Keyed on the
    // address and a stable signature, so a manually collapsed group is not reopened on every render.
    const visitKey = pageUrl + '|' + activeGroupSignature;
    const [seenVisitKey, setSeenVisitKey] = useState(visitKey);
    if (seenVisitKey !== visitKey) {
        setSeenVisitKey(visitKey);
        setOpenGroups(withActiveOpen);
        setQuery('');
    }

    // The collapsed rail has no filter field, so collapsing clears the filter.
    const [seenCollapsed, setSeenCollapsed] = useState(collapsed);
    if (seenCollapsed !== collapsed) {
        setSeenCollapsed(collapsed);
        if (collapsed) setQuery('');
    }

    // Deep items in long groups can sit below the fold; keep the current page visible after each visit.
    const activeGroupOpen = activeGroupKeys.every(
        (key) => shownGroups[key] ?? false,
    );
    useEffect(() => {
        if (activeGroupOpen)
            navRef.current
                ?.querySelector('[aria-current="page"]')
                ?.scrollIntoView({ block: 'nearest' });
    }, [pageUrl, collapsed, activeGroupOpen]);

    useEffect(() => {
        try {
            window.localStorage.setItem(
                SIDEBAR_GROUPS_STORAGE_KEY,
                JSON.stringify(openGroups),
            );
        } catch {
            /* Navigation remains usable without storage. */
        }
    }, [openGroups]);

    useEffect(() => {
        if (!collapsed && focusSearchAfterExpand.current) {
            focusSearchAfterExpand.current = false;
            searchRef.current?.focus();
        }
    }, [collapsed]);

    const matches = (item: NavItem) =>
        t(item.labelKey).toLocaleLowerCase().includes(normalizedQuery);
    const matchingItems = (group: NavGroup) =>
        !normalizedQuery ||
        t(group.labelKey).toLocaleLowerCase().includes(normalizedQuery)
            ? group.items
            : group.items.filter(matches);
    const matchingGroups = allGroups.filter(
        (group) => matchingItems(group).length > 0,
    );

    // "/" jumps to the menu filter (desktop sidebar only; ignored while typing elsewhere).
    useEffect(() => {
        if (!onToggleCollapse) return;
        function onKey(event: KeyboardEvent) {
            if (
                event.key !== '/' ||
                event.ctrlKey ||
                event.metaKey ||
                event.altKey
            )
                return;
            const target = event.target as HTMLElement | null;
            if (
                target &&
                (target.isContentEditable ||
                    ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
            )
                return;
            if (!navRef.current?.offsetParent) return;
            event.preventDefault();
            if (collapsed) {
                focusSearchAfterExpand.current = true;
                onToggleCollapse?.();
            } else {
                searchRef.current?.focus();
            }
        }
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [collapsed, onToggleCollapse]);

    /** Marks the part of a label that matched the filter, so it is clear why an item is listed. */
    function highlight(text: string): ReactNode {
        const index = normalizedQuery
            ? text.toLocaleLowerCase().indexOf(normalizedQuery)
            : -1;
        if (index < 0) return text;
        return (
            <>
                {text.slice(0, index)}
                <mark className="rounded-xs bg-(--sidebar-accent-wash) font-semibold text-(--sidebar-fg)">
                    {text.slice(index, index + normalizedQuery.length)}
                </mark>
                {text.slice(index + normalizedQuery.length)}
            </>
        );
    }

    /**
     * nested: a page inside a group (quieter, with a smaller icon). compact: the icon-only collapsed rail.
     * onNavigate: closes a collapsed-rail flyout after choosing a page.
     */
    function renderLink(
        item: NavItem,
        {
            nested = false,
            compact = collapsed,
            onNavigate,
        }: {
            nested?: boolean;
            compact?: boolean;
            onNavigate?: () => void;
        } = {},
    ) {
        const selected = currentItem === item.href;
        const Icon = item.icon;
        const label = t(item.labelKey);
        return (
            <li key={item.href}>
                <Link
                    href={item.href}
                    onClick={() => {
                        onClose?.();
                        onNavigate?.();
                    }}
                    aria-current={selected ? 'page' : undefined}
                    aria-label={compact ? label : undefined}
                    title={compact ? label : undefined}
                    className={[
                        // scroll-my keeps an auto-scrolled current page clear of the list's fading edges.
                        'relative flex scroll-my-12 items-center gap-3 rounded-lg text-sm transition-colors duration-150',
                        focusRing,
                        hoverSurface,
                        compact
                            ? 'mx-auto h-10 w-10 justify-center'
                            : nested
                              ? 'min-h-8 px-3 py-1.5'
                              : 'min-h-9 px-3 py-2',
                        selected
                            ? selectedSurface + ' font-semibold'
                            : nested
                              ? 'text-(--sidebar-muted) hover:text-(--sidebar-fg)'
                              : 'font-medium text-(--sidebar-fg)',
                    ].join(' ')}
                >
                    {selected && (
                        <span
                            aria-hidden="true"
                            data-nav-indicator
                            className="absolute inset-y-1.5 inset-s-0 w-0.5 rounded-full bg-(--sidebar-accent)"
                        />
                    )}
                    <Icon
                        aria-hidden="true"
                        className={
                            nested && !compact
                                ? [
                                      'h-4 w-4 shrink-0',
                                      selected ? '' : 'opacity-80',
                                  ].join(' ')
                                : 'h-[18px] w-[18px] shrink-0'
                        }
                    />
                    {!compact && (
                        <span className="min-w-0 flex-1 leading-snug wrap-break-word whitespace-normal">
                            {highlight(label)}
                        </span>
                    )}
                </Link>
            </li>
        );
    }

    function renderGroupItems(items: NavItem[], onNavigate?: () => void) {
        return (
            <ul className="space-y-px">
                {items.map((item) =>
                    renderLink(item, {
                        nested: true,
                        compact: false,
                        onNavigate,
                    }),
                )}
            </ul>
        );
    }

    function renderSection(labelKey: string, groups: NavGroup[]) {
        if (!groups.some((group) => matchingItems(group).length)) return null;
        return (
            <div key={labelKey} className="mb-4 last:mb-0">
                {collapsed ? (
                    <div className="mx-3 my-2 border-t border-(--sidebar-border)" />
                ) : (
                    <p className="px-3 pt-1 pb-1.5 text-[11px] leading-relaxed font-semibold tracking-[0.08em] text-(--sidebar-muted) uppercase">
                        {t(labelKey)}
                    </p>
                )}
                <div className="space-y-0.5">{groups.map(renderGroup)}</div>
            </div>
        );
    }

    function renderGroup(group: NavGroup) {
        const items = matchingItems(group);
        if (!items.length) return null;
        const Icon = group.icon;
        const selected = activeGroupKeys.includes(group.key);
        const isOpen = Boolean(
            normalizedQuery || (shownGroups[group.key] ?? false),
        );
        const panelId = instanceId + '-group-' + group.key;
        const label = t(group.labelKey);
        // A group the person sees one page of is just that page's link.
        if (group.items.length === 1) {
            return <ul key={group.key}>{renderLink(group.items[0])}</ul>;
        }
        if (collapsed) {
            // The rail stays compact: a group opens as a flyout of its pages instead of expanding the sidebar.
            return (
                <Popover key={group.key}>
                    <PopoverButton
                        title={label}
                        aria-label={label}
                        className={[
                            'mx-auto flex h-10 w-10 items-center justify-center rounded-md transition-colors data-open:bg-(--sidebar-hover)',
                            focusRing,
                            hoverSurface,
                            selected ? selectedSurface : 'text-(--sidebar-fg)',
                        ].join(' ')}
                    >
                        <Icon
                            className="h-[18px] w-[18px] shrink-0"
                            aria-hidden="true"
                        />
                    </PopoverButton>
                    <PopoverPanel
                        anchor="right start"
                        transition
                        className="z-50 w-64 rounded-panel border border-(--sidebar-border) bg-(--sidebar-bg) p-2 text-(--sidebar-fg) shadow-lg outline-hidden transition duration-100 ease-out [--anchor-gap:12px] [--anchor-padding:8px] data-closed:-translate-x-1 data-closed:opacity-0"
                    >
                        {({ close }) => (
                            <>
                                <p className="px-2.5 pt-1 pb-1.5 text-[11px] font-semibold tracking-[0.08em] text-(--sidebar-muted) uppercase">
                                    {label}
                                </p>
                                {renderGroupItems(items, close)}
                            </>
                        )}
                    </PopoverPanel>
                </Popover>
            );
        }
        return (
            <div key={group.key}>
                <button
                    type="button"
                    aria-expanded={isOpen}
                    aria-controls={panelId}
                    onClick={() => {
                        if (!normalizedQuery)
                            setOpenGroups((previous) => ({
                                ...previous,
                                [group.key]: !isOpen,
                            }));
                    }}
                    className={[
                        'flex min-h-9 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-(--sidebar-fg) transition-colors duration-150',
                        focusRing,
                        hoverSurface,
                        // Only the page itself carries the selected surface; its group is marked by weight and an accent icon.
                        selected ? 'font-semibold' : 'font-medium',
                    ].join(' ')}
                >
                    <Icon
                        className={[
                            'h-[18px] w-[18px] shrink-0',
                            selected ? 'text-(--sidebar-accent)' : '',
                        ].join(' ')}
                        aria-hidden="true"
                    />
                    <span className="min-w-0 flex-1 leading-snug wrap-break-word whitespace-normal">
                        {highlight(label)}
                    </span>
                    <ChevronDown
                        className={[
                            'h-3.5 w-3.5 shrink-0 text-(--sidebar-muted) transition-transform',
                            isOpen ? '' : '-rotate-90',
                        ].join(' ')}
                        aria-hidden="true"
                    />
                </button>
                <div id={panelId} hidden={!isOpen}>
                    <div className="ms-[21px] mt-0.5 mb-1.5 border-s border-(--sidebar-border) ps-2">
                        {renderGroupItems(items)}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div
            data-sidebar
            className="flex h-full min-h-0 w-full flex-col border-e border-(--sidebar-border) bg-(--sidebar-bg) text-(--sidebar-fg)"
        >
            {/* The brand row is as tall as the top bar, so their bottom edges make one line across the page. */}
            <div
                data-sidebar-brand
                className={
                    collapsed
                        ? 'flex h-16 shrink-0 items-center justify-center border-b border-(--sidebar-border)'
                        : stackedBrand
                          ? 'shrink-0 border-b border-(--sidebar-border) px-4 py-3.5'
                          : 'flex h-16 shrink-0 items-center border-b border-(--sidebar-border) px-4'
                }
            >
                <div className="flex w-full min-w-0 items-center gap-2">
                    <Link
                        href={homeNav.href}
                        onClick={onClose}
                        aria-label={appName}
                        title={appName}
                        className={[
                            'flex min-w-0 flex-1 gap-3 rounded-lg',
                            focusRing,
                            stackedBrand
                                ? 'flex-col items-center text-center'
                                : 'items-center',
                        ].join(' ')}
                    >
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-(--sidebar-bg) p-1 ring-1 ring-(--sidebar-border)">
                            <ApplicationLogo className="h-full w-full object-contain" />
                        </span>
                        {!collapsed && (
                            <div className="min-w-0">
                                <p
                                    className={[
                                        'text-sm leading-snug font-semibold',
                                        // One line beside the logo (the full name is the link's title); the stacked block wraps.
                                        stackedBrand
                                            ? 'wrap-break-word'
                                            : 'truncate',
                                    ].join(' ')}
                                >
                                    {appName}
                                </p>
                                <p className="mt-0.5 truncate text-[10px] font-semibold tracking-[0.08em] text-(--sidebar-muted) uppercase">
                                    {shortName}
                                </p>
                                {stackedBrand && orgName && (
                                    <p className="mt-1 text-xs leading-relaxed wrap-break-word whitespace-normal text-(--sidebar-muted)">
                                        {orgName}
                                    </p>
                                )}
                            </div>
                        )}
                    </Link>
                    {onClose && (
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label={t('shell.nav.close_menu')}
                            className={[
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-md',
                                focusRing,
                                hoverSurface,
                            ].join(' ')}
                        >
                            <X
                                className="h-[18px] w-[18px]"
                                aria-hidden="true"
                            />
                        </button>
                    )}
                </div>
            </div>

            <div className={collapsed ? 'px-2 pt-3' : 'px-3 pt-3'}>
                {collapsed ? (
                    <button
                        type="button"
                        aria-label={t('shell.nav.filter_menu')}
                        title={t('shell.nav.filter_menu')}
                        onClick={() => {
                            focusSearchAfterExpand.current = true;
                            onToggleCollapse?.();
                        }}
                        className={[
                            'mx-auto flex h-10 w-10 items-center justify-center rounded-md text-(--sidebar-muted)',
                            focusRing,
                            hoverSurface,
                        ].join(' ')}
                    >
                        <SearchIcon
                            className="h-[18px] w-[18px]"
                            aria-hidden="true"
                        />
                    </button>
                ) : (
                    <div className="flex items-center gap-2 rounded-md border border-(--sidebar-border) bg-(--sidebar-hover) px-2.5 transition-colors focus-within:border-(--sidebar-accent) focus-within:ring-2 focus-within:ring-(--sidebar-accent-wash)">
                        <SearchIcon
                            className="h-4 w-4 shrink-0 text-(--sidebar-muted)"
                            aria-hidden="true"
                        />
                        <input
                            ref={searchRef}
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Escape' && query) {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    setQuery('');
                                }
                            }}
                            aria-label={t('shell.nav.filter_menu')}
                            placeholder={t('shell.nav.filter_menu')}
                            aria-keyshortcuts={
                                onToggleCollapse ? '/' : undefined
                            }
                            className="peer h-9 w-full min-w-0 border-0 bg-transparent p-0 text-sm text-(--sidebar-fg) outline-hidden placeholder:text-(--sidebar-muted) focus:ring-0 focus-visible:outline-hidden"
                        />
                        {onToggleCollapse && !query && (
                            <kbd
                                aria-hidden="true"
                                className="shrink-0 rounded-sm border border-(--sidebar-border) px-1.5 font-sans text-[10px] leading-4 font-medium text-(--sidebar-muted) peer-focus:hidden"
                            >
                                /
                            </kbd>
                        )}
                    </div>
                )}
            </div>

            <nav
                ref={navRef}
                className="sidebar-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain mask-[linear-gradient(to_bottom,transparent,#000_12px,#000_94%,transparent)] px-3 pt-3 pb-6 [-webkit-mask-image:linear-gradient(to_bottom,transparent,#000_12px,#000_94%,transparent)]"
                aria-label={t('shell.nav.main_navigation')}
            >
                {showHome && (!normalizedQuery || matches(homeNav)) && (
                    <ul className="mb-4 space-y-px">{renderLink(homeNav)}</ul>
                )}
                {sections.map((section) =>
                    renderSection(section.labelKey, section.groups),
                )}
                {normalizedQuery &&
                    !matchingGroups.length &&
                    !(showHome && matches(homeNav)) && (
                        <p
                            role="status"
                            className="px-3 py-5 text-sm leading-relaxed text-(--sidebar-muted)"
                        >
                            {t('shell.nav.no_results')}
                        </p>
                    )}
            </nav>

            <div className="shrink-0 border-t border-(--sidebar-border) p-3">
                {/* Non-production environments are flagged where every page shows it, so test data is never mistaken for live. */}
                {environmentLabel &&
                    environmentLabel.toLowerCase() !== 'production' &&
                    (collapsed ? (
                        <span
                            title={environmentLabel}
                            aria-label={environmentLabel}
                            className="mx-auto mb-2 block h-2 w-2 rounded-full bg-brand-gold"
                        />
                    ) : (
                        <p className="px-1.5 pb-2">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-gold/15 px-2 py-0.5 text-[11px] font-semibold text-(--sidebar-fg) ring-1 ring-brand-gold/50 ring-inset">
                                <span
                                    className="h-1.5 w-1.5 rounded-full bg-brand-gold"
                                    aria-hidden="true"
                                />
                                {environmentLabel}
                            </span>
                        </p>
                    ))}
                {/* Who is signed in, with the way out beside it. */}
                <div
                    className={
                        collapsed
                            ? 'flex flex-col items-center gap-1'
                            : 'flex items-center gap-1'
                    }
                >
                    <Link
                        href={profileEdit().url}
                        onClick={onClose}
                        aria-label={
                            t('shell.account.profile_settings') +
                            ': ' +
                            displayName
                        }
                        title={collapsed ? displayName : undefined}
                        className={[
                            'flex min-w-0 items-center gap-3 rounded-lg transition-colors duration-150',
                            focusRing,
                            hoverSurface,
                            collapsed
                                ? 'h-10 w-10 justify-center'
                                : 'flex-1 p-1.5',
                        ].join(' ')}
                    >
                        <UserAvatar
                            src={user?.avatar}
                            name={displayName}
                            size={32}
                            className="bg-(--sidebar-hover)! text-(--sidebar-fg)! ring-1 ring-(--sidebar-border)"
                        />
                        {!collapsed && (
                            <span className="min-w-0">
                                <span className="block truncate text-sm leading-snug font-semibold">
                                    {displayName}
                                </span>
                                {role && (
                                    <span className="block truncate text-xs leading-snug text-(--sidebar-muted)">
                                        {role}
                                    </span>
                                )}
                            </span>
                        )}
                    </Link>
                    <Link
                        href={logout().url}
                        method="post"
                        as="button"
                        // Signed out, no page from this session may come back from the cache.
                        onClick={() => router.flushAll()}
                        aria-label={t('shell.account.sign_out')}
                        title={t('shell.account.sign_out')}
                        className={[
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-(--sidebar-muted) transition-colors duration-150 hover:text-(--sidebar-fg)',
                            focusRing,
                            hoverSurface,
                        ].join(' ')}
                    >
                        <LogOut
                            className="h-[18px] w-[18px]"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </div>
        </div>
    );
}
