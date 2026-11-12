import {
    Dialog,
    DialogBackdrop,
    DialogPanel,
    DialogTitle,
} from '@headlessui/react';
import { usePage } from '@inertiajs/react';
import { useLayoutEffect, useState } from 'react';
import type { PropsWithChildren, ReactNode } from 'react';
import { SupportContacts } from '@/components/support-contacts';
import AppHeader from '@/shell/Components/AppHeader';
import AppSidebar from '@/shell/Components/AppSidebar';
import Breadcrumbs from '@/shell/Components/Breadcrumbs';
import type { Crumb } from '@/shell/Components/Breadcrumbs';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import AppToaster from '@/shell/Components/ui/AppToaster';
import LocalizedUiProvider from '@/shell/Components/ui/LocalizedUiProvider';
import { LocaleProvider } from '@/shell/contexts/LocaleContext';
import { useLocale } from '@/shell/hooks/useLocale';
import { useSystemSettings } from '@/shell/hooks/useSystemSettings';
import {
    darkThemeVariant,
    hoverVariant,
    isLight,
    readableForeground,
    sidebarAccent,
} from '@/shell/lib/brandColor';
import { resolveUiStyle } from '@/shell/lib/theme';
import { buttonClassName } from '@app/ui';

const SIDEBAR_STORAGE_KEY = 'heavy-sidebar-collapsed';

/** What the shell writes onto <html> from System settings → Appearance. */
const SHELL_ROOT_PROPERTIES = [
    '--color-primary-strong',
    '--color-primary-soft',
    '--color-primary-hover-strong',
    '--color-primary-hover-soft',
    '--color-on-primary-strong',
    '--color-on-primary-soft',
    '--color-secondary',
    '--color-accent-strong',
    '--color-accent-soft',
    '--sidebar-bg-light',
    '--sidebar-bg-dark',
    '--sidebar-fg-light',
    '--sidebar-fg-dark',
    '--sidebar-accent-light',
    '--sidebar-accent-dark',
] as const;
/*
 * data-* attributes on <html>. `shell` switches on the shell's tokens in
 * resources/css/shell.css. The others are already written for the first
 * paint by App\Domain\Settings\ThemeStyle; the shell refreshes them from the
 * settings and puts the previous values back when it goes away, so the pages
 * on the older layout keep theirs.
 */
const SHELL_ROOT_DATASET = [
    'shell',
    'uiStyle',
    'buttonStyle',
    'cardRadius',
    'tableDensity',
    'animations',
] as const;
// Page chrome and content share one measure, so titles line up with the content below them
// and very wide screens keep a readable line length.
const CONTENT_WIDTH = 'mx-auto w-full max-w-[1600px]';
// Create and edit forms share one measure too; the prop keeps it changeable in one place.
const FORM_WIDTH = 'w-full';

export type ContentWidth = 'full' | 'form';

type Props = PropsWithChildren<{
    /** The heading band: usually a <PageHeader />. Breadcrumbs sit above it. */
    header?: ReactNode;
    variant?: 'default' | 'portal';
    contentWidth?: ContentWidth;
    /** A trail for a page the menu does not cover (after home); otherwise it comes from the menu. */
    breadcrumbs?: Crumb[];
    /**
     * Pages from the older layout (layouts/app-layout.tsx) bring their own
     * padding, heading band and breadcrumbs: the shell gives them its
     * sidebar, top bar and footer and renders them as they are.
     */
    bare?: boolean;
}>;

/**
 * The signed-in shell, ported from the source system: fixed sidebar (collapsible to an
 * icon rail, a drawer below `lg`), top bar, breadcrumbs, the heading band,
 * the page and a quiet footer, with the brand colours from System settings.
 */
export default function AuthenticatedLayout(props: Props) {
    return (
        <LocaleProvider>
            <LocalizedUiProvider>
                {/* useConfirm() works on every page in the shell. */}
                <ConfirmProvider>
                    <Shell {...props} />
                </ConfirmProvider>
            </LocalizedUiProvider>
        </LocaleProvider>
    );
}

function Shell({
    header,
    children,
    variant = 'default',
    contentWidth = 'full',
    breadcrumbs,
    bare = false,
}: Props) {
    const { locale, t } = useLocale();
    const { getBoolean, getString } = useSystemSettings();
    const { url: pageUrl } = usePage();

    /* Defaults mirror the brand tokens in app.css. */
    const primary = getString('appearance.primary_color', '#122170');
    const secondary = getString('appearance.secondary_color', '#1d3084');
    const accent = getString('appearance.accent_color', '#d12908');
    const sidebarColor = getString('appearance.sidebar_color', '#ffffff');
    const uiStyle = getString('appearance.ui_style', 'swiss');
    // A person's own style (ThemeToggle) wins over the organisation's while switching is allowed.
    const allowUserTheme = getBoolean(
        'appearance.allow_user_theme_switching',
        true,
    );
    const buttonStyle = getString('appearance.button_style', 'auto');
    const cardRadius = getString('appearance.card_radius', 'auto');
    const tableDensity = getString('appearance.table_density', 'comfortable');
    const enableAnimations = getBoolean(
        'appearance.enable_ui_animations',
        true,
    );
    const showBreadcrumbs = getBoolean('appearance.show_breadcrumbs', true);
    const sidebarCompactDefault = getBoolean(
        'appearance.sidebar_compact_default',
        false,
    );
    const maintenanceEnabled = getBoolean(
        'security.maintenance_banner_enabled',
        false,
    );
    const maintenanceMessage =
        locale === 'am'
            ? getString('security.maintenance_banner_message_am')
            : getString('security.maintenance_banner_message_en');

    const [sidebarOpen, setSidebarOpen] = useState(false);

    const [sidebarCollapsed, setSidebarCollapsed] = useState<boolean>(() => {
        try {
            const stored = localStorage.getItem(SIDEBAR_STORAGE_KEY);
            if (stored !== null) return stored === 'true';
        } catch {
            /* Storage unavailable: the organisation default applies. */
        }
        return sidebarCompactDefault;
    });
    const collapsed = sidebarCollapsed;

    const toggleSidebarCollapse = () => {
        setSidebarCollapsed((prev) => {
            const next = !prev;
            try {
                localStorage.setItem(SIDEBAR_STORAGE_KEY, String(next));
            } catch {
                /* Kept for this visit only. */
            }
            return next;
        });
    };

    // Inertia keeps the shell mounted between visits. Close the mobile drawer
    // after every successful navigation so the destination is immediately visible.
    const [seenUrl, setSeenUrl] = useState(pageUrl);
    if (seenUrl !== pageUrl) {
        setSeenUrl(pageUrl);
        setSidebarOpen(false);
    }

    // Before paint, so the first frame already has the shell's colours.
    useLayoutEffect(() => {
        const root = document.documentElement;
        const previous = Object.fromEntries(
            SHELL_ROOT_DATASET.map((key) => [key, root.dataset[key]]),
        );

        /* Write the two theme variants, never `--color-primary` itself: an
           inline value would outrank the `.dark` rule in shell.css and leave
           dark mode stuck on the light-theme colour. */
        root.style.setProperty('--color-primary-strong', primary);
        root.style.setProperty(
            '--color-primary-soft',
            darkThemeVariant(primary),
        );
        root.style.setProperty(
            '--color-primary-hover-strong',
            hoverVariant(primary),
        );
        root.style.setProperty(
            '--color-primary-hover-soft',
            hoverVariant(darkThemeVariant(primary)),
        );
        // Text on a primary fill: the lighter dark-theme variant of a bright
        // brand colour needs dark text, not white.
        root.style.setProperty(
            '--color-on-primary-strong',
            readableForeground(primary),
        );
        root.style.setProperty(
            '--color-on-primary-soft',
            readableForeground(darkThemeVariant(primary)),
        );
        root.style.setProperty('--color-secondary', secondary);
        root.style.setProperty('--color-accent-strong', accent);
        root.style.setProperty('--color-accent-soft', darkThemeVariant(accent));

        /*
         * Sidebar surface, per theme. Light mode uses the configured colour as
         * chosen; dark mode only honours it when it is already dark. The
         * foreground is derived from whichever background ends up in play.
         */
        const sidebarDark = isLight(sidebarColor) ? '#0b1020' : sidebarColor;
        root.style.setProperty('--sidebar-bg-light', sidebarColor);
        root.style.setProperty('--sidebar-bg-dark', sidebarDark);
        root.style.setProperty(
            '--sidebar-fg-light',
            readableForeground(sidebarColor),
        );
        root.style.setProperty(
            '--sidebar-fg-dark',
            readableForeground(sidebarDark),
        );

        /* Selected-item colour, kept legible against whatever the sidebar is. */
        root.style.setProperty(
            '--sidebar-accent-light',
            sidebarAccent(primary, sidebarColor),
        );
        root.style.setProperty(
            '--sidebar-accent-dark',
            sidebarAccent(darkThemeVariant(primary), sidebarDark),
        );

        root.dataset.shell = 'admin';
        root.dataset.uiStyle = resolveUiStyle(uiStyle, allowUserTheme);
        root.dataset.buttonStyle = buttonStyle;
        root.dataset.cardRadius = cardRadius;
        root.dataset.tableDensity = tableDensity;
        root.dataset.animations = enableAnimations ? 'on' : 'off';

        /* Inertia swaps pages without reloading, so the root element outlives
           this shell. Take the shell's values back off when it goes away, so
           the pages on the older layout look as before. */
        return () => {
            for (const name of SHELL_ROOT_PROPERTIES)
                root.style.removeProperty(name);
            for (const key of SHELL_ROOT_DATASET) {
                const value = previous[key];
                if (value === undefined) delete root.dataset[key];
                else root.dataset[key] = value;
            }
        };
    }, [
        primary,
        secondary,
        accent,
        sidebarColor,
        uiStyle,
        allowUserTheme,
        buttonStyle,
        cardRadius,
        tableDensity,
        enableAnimations,
    ]);

    return (
        <div
            className={`${variant === 'portal' ? 'portal-shell' : 'admin-shell'} min-h-screen bg-(--app-background)`}
        >
            <a
                href="#main-content"
                className={buttonClassName({
                    variant: 'primary',
                    className:
                        'sr-only fixed top-4 left-4 z-60 focus:not-sr-only',
                })}
            >
                {t('common.skip_to_content')}
            </a>
            <AppToaster />

            {/* Desktop sidebar — fixed, does not scroll with page content */}
            <div className="hidden lg:block">
                <div
                    className={[
                        'fixed inset-y-0 left-0 z-30 flex h-screen flex-col',
                        'overflow-hidden transition-[width] duration-200 ease-in-out',
                        collapsed ? 'w-16' : 'w-70',
                    ].join(' ')}
                >
                    <AppSidebar
                        collapsed={collapsed}
                        onToggleCollapse={toggleSidebarCollapse}
                    />
                </div>
            </div>

            {/* Mobile sidebar drawer. Headless UI owns focus trapping, Escape,
                scroll locking and focus restoration. */}
            <Dialog
                open={sidebarOpen}
                onClose={setSidebarOpen}
                className="relative z-50 lg:hidden"
            >
                <DialogBackdrop
                    transition
                    className="fixed inset-0 bg-black/50 transition-opacity duration-200 data-closed:opacity-0"
                />
                <div className="fixed inset-0 flex">
                    <DialogPanel
                        transition
                        className="h-full w-[min(20rem,calc(100vw-3rem))] overflow-hidden rounded-e-(--radius-panel) border-e border-(--app-border) shadow-xl transition duration-200 ease-out data-closed:-translate-x-full"
                    >
                        <DialogTitle className="sr-only">
                            {t('shell.nav.main_navigation')}
                        </DialogTitle>
                        <AppSidebar onClose={() => setSidebarOpen(false)} />
                    </DialogPanel>
                </div>
            </Dialog>

            {/* Main area — offset by sidebar width on desktop, scrolls independently */}
            <div
                className={[
                    'flex min-h-screen flex-col',
                    'transition-[margin-left] duration-200 ease-in-out',
                    collapsed ? 'lg:ml-16' : 'lg:ml-70',
                ].join(' ')}
            >
                <AppHeader
                    onMenuClick={() => setSidebarOpen(true)}
                    onToggleSidebar={toggleSidebarCollapse}
                    sidebarCollapsed={collapsed}
                />

                {maintenanceEnabled && maintenanceMessage && (
                    <div
                        role="status"
                        className="shrink-0 border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
                    >
                        {maintenanceMessage}
                    </div>
                )}

                {/* Breadcrumbs sit above the title they lead to, in the same band. */}
                {header && (
                    <div className="app-page-heading app-dot-grid shrink-0 border-b border-(--app-border) bg-(--app-surface)">
                        <div
                            className={
                                CONTENT_WIDTH + ' px-4 py-5 sm:px-6 lg:px-8'
                            }
                        >
                            {showBreadcrumbs && (
                                <Breadcrumbs
                                    className="mb-2.5"
                                    items={breadcrumbs}
                                />
                            )}
                            {header}
                        </div>
                    </div>
                )}

                {bare ? (
                    <main
                        id="main-content"
                        tabIndex={-1}
                        className="relative flex min-w-0 flex-1 flex-col overflow-x-clip outline-hidden"
                    >
                        {children}
                    </main>
                ) : (
                    <main
                        id="main-content"
                        tabIndex={-1}
                        className="min-w-0 flex-1 px-4 py-6 outline-hidden sm:px-6 lg:px-8"
                    >
                        <div className={CONTENT_WIDTH}>
                            {!header && showBreadcrumbs && (
                                <Breadcrumbs
                                    className="mb-4"
                                    items={breadcrumbs}
                                />
                            )}
                            {contentWidth === 'form' ? (
                                <div className={FORM_WIDTH}>{children}</div>
                            ) : (
                                children
                            )}
                        </div>
                    </main>
                )}

                <footer className="shrink-0 border-t border-(--app-border) px-4 py-3 text-xs text-(--app-muted-foreground) sm:px-6 lg:px-8">
                    <div
                        className={
                            CONTENT_WIDTH +
                            ' flex flex-wrap items-center justify-between gap-x-6 gap-y-1'
                        }
                    >
                        <p>{t('common.authority')}</p>
                        <SupportContacts />
                    </div>
                </footer>
            </div>
        </div>
    );
}
