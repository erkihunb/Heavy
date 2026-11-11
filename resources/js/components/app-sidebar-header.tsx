import { usePage } from '@inertiajs/react';
import { AltArrowDownIcon } from '@solar-icons/react/linear/alt-arrow-down';
import { HamburgerMenuIcon } from '@solar-icons/react/linear/hamburger-menu';
import { MagnifierIcon } from '@solar-icons/react/linear/magnifier';
import { SidebarMinimalisticIcon } from '@solar-icons/react/linear/sidebar-minimalistic';
import { useEffect, useState } from 'react';
import { DualDate } from '@/components/dual-date';
import { LanguageSwitcher } from '@/components/language-switcher';
import { NavSearch } from '@/components/nav-search';
import { ThemeToggle } from '@/components/theme-toggle';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSidebar } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/* Every toolbar control is 36px on the control radius, so the row reads as one instrument. */
const ICON_BUTTON =
    'flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors duration-150 hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-card';

/**
 * The EUISIS top bar: menu and sidebar toggles, navigation search, today's
 * date in both calendars, theme and language switches, and the account menu.
 */
export function AppSidebarHeader() {
    const { auth, today, appearance } = usePage().props;
    const { t } = useTranslation();
    const { toggleSidebar, state, isMobile } = useSidebar();
    const [searchOpen, setSearchOpen] = useState(false);
    const [shortcut] = useState(() =>
        typeof navigator !== 'undefined' &&
        /Mac|iPhone|iPad/.test(navigator.platform)
            ? '⌘ K'
            : 'Ctrl K',
    );

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'k' &&
                !event.repeat
            ) {
                event.preventDefault();
                setSearchOpen((open) => !open);
            }
        };

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, []);

    return (
        <>
            <NavSearch open={searchOpen} onOpenChange={setSearchOpen} />
            <header className="sticky top-0 z-20 flex h-16 min-w-0 shrink-0 items-center justify-between gap-2 border-b border-border bg-card px-3 sm:gap-4 sm:px-6 lg:px-8">
                <div className="flex min-w-0 items-center gap-1 sm:flex-1 sm:gap-2">
                    <button
                        type="button"
                        onClick={toggleSidebar}
                        aria-label={
                            isMobile
                                ? t('nav.open_menu')
                                : state === 'collapsed'
                                  ? t('nav.expand_sidebar')
                                  : t('nav.collapse_sidebar')
                        }
                        aria-expanded={
                            isMobile ? undefined : state === 'expanded'
                        }
                        className={ICON_BUTTON}
                    >
                        {isMobile ? (
                            <HamburgerMenuIcon
                                className="size-5"
                                aria-hidden="true"
                            />
                        ) : (
                            <SidebarMinimalisticIcon
                                className="size-5"
                                aria-hidden="true"
                            />
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={() => setSearchOpen(true)}
                        aria-label={t('nav.search')}
                        aria-haspopup="dialog"
                        className={cn(ICON_BUTTON, 'sm:hidden')}
                    >
                        <MagnifierIcon className="size-5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setSearchOpen(true)}
                        aria-label={t('nav.search')}
                        aria-haspopup="dialog"
                        aria-keyshortcuts="Control+k Meta+k"
                        className="hidden h-9 w-full max-w-sm items-center gap-2.5 rounded-md border border-border bg-muted px-3 text-sm text-muted-foreground transition-colors duration-150 hover:border-border-strong hover:bg-card hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none sm:flex"
                    >
                        <MagnifierIcon
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span className="min-w-0 flex-1 truncate text-start">
                            {t('nav.search_placeholder')}
                        </span>
                        <kbd
                            aria-hidden="true"
                            className="hidden shrink-0 rounded-xs border border-border bg-card px-1.5 py-0.5 font-sans text-[10px] font-medium lg:inline"
                        >
                            {shortcut}
                        </kbd>
                    </button>
                </div>

                <div className="flex shrink-0 items-center gap-1 sm:gap-1.5">
                    <p className="mr-2 hidden items-center text-sm xl:flex">
                        <DualDate date={today} />
                    </p>
                    <ThemeToggle className={ICON_BUTTON} />
                    {appearance.showLanguageSwitcher && <LanguageSwitcher />}
                    <span
                        aria-hidden="true"
                        className="mx-1 hidden h-6 w-px bg-border sm:block"
                    />
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            aria-label={`${t('nav.account_menu')}: ${auth.user.name}`}
                            className="flex h-10 items-center gap-2.5 rounded-md px-1 text-start text-foreground transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none sm:px-1.5"
                        >
                            <span className="flex items-center gap-2.5 [&>div]:hidden lg:[&>div]:grid">
                                <UserInfo user={auth.user} showRole />
                            </span>
                            <AltArrowDownIcon
                                className="hidden size-4 shrink-0 text-muted-foreground sm:block"
                                aria-hidden="true"
                            />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="w-72 max-w-[calc(100vw-1rem)] p-0"
                        >
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>
        </>
    );
}
