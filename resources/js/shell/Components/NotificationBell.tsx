import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { BellIcon } from '@/shell/Components/NavIcons';
import { useLocale } from '@/shell/hooks/useLocale';

/**
 * The top bar's bell, as a local stand-in for the source system's notification feed:
 * same button and panel, always in its empty state. Heavy sends its notices
 * by email, SMS and Telegram; there is no in-app feed to read yet. To add
 * one, fetch the feed when the panel opens and render it in place of the
 * empty message (NotificationBell shows how).
 */
export default function NotificationBell() {
    const { t } = useLocale();

    return (
        <Popover className="relative">
            <PopoverButton
                aria-label={t('shell.notifications.title')}
                className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-(--app-muted-foreground) transition-colors duration-150 hover:bg-(--app-surface-muted) hover:text-(--app-foreground) focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-hidden"
            >
                <BellIcon className="h-5 w-5" aria-hidden="true" />
            </PopoverButton>
            <PopoverPanel
                anchor="bottom end"
                className="z-50 w-96 max-w-[calc(100vw-1rem)] rounded-panel border border-(--app-border) bg-(--app-surface) text-(--app-foreground) shadow-xl outline-hidden [--anchor-gap:8px] [--anchor-padding:8px]"
            >
                <div className="flex items-center justify-between gap-3 border-b border-(--app-border) p-4">
                    <h2 className="text-sm font-semibold">
                        {t('shell.notifications.title')}
                    </h2>
                </div>
                <div className="flex flex-col items-center gap-2 p-8 text-center">
                    <span
                        className="flex h-12 w-12 items-center justify-center rounded-full bg-(--app-surface-muted) text-(--app-muted-foreground)"
                        aria-hidden="true"
                    >
                        <BellIcon className="h-6 w-6" />
                    </span>
                    <p className="text-sm font-semibold">
                        {t('shell.notifications.empty')}
                    </p>
                    <p className="max-w-64 text-xs text-(--app-muted-foreground)">
                        {t('shell.notifications.empty_hint')}
                    </p>
                </div>
            </PopoverPanel>
        </Popover>
    );
}
