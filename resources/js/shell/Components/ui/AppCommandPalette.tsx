import {
    Dialog,
    Transition,
    TransitionChild,
    DialogPanel,
} from '@headlessui/react';
import { router } from '@inertiajs/react';
import { Command } from 'cmdk';
import { useEffect, useRef, useState, Fragment } from 'react';
import { navigation } from '@/config/navigation';
import type { NavItem } from '@/config/navigation';
import { SearchIcon as Search } from '@/shell/Components/NavIcons';
import { useCan } from '@/shell/hooks/useCan';
import { useLocale } from '@/shell/hooks/useLocale';
import { isPermitted, permittedSections } from '@/shell/lib/navigation';

interface Props {
    open: boolean;
    onClose: () => void;
}

/**
 * Ctrl+K / ⌘K: jump to any page the person may open. The entries are
 * the menu itself (config/navigation.ts), grouped as in the sidebar, so the
 * palette and the sidebar never disagree.
 */
export default function AppCommandPalette({ open, onClose }: Props) {
    const { t } = useLocale();
    const { can } = useCan();
    const [search, setSearch] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    // Each opening starts with an empty search.
    const [wasOpen, setWasOpen] = useState(open);
    if (wasOpen !== open) {
        setWasOpen(open);
        if (open) setSearch('');
    }

    // Focus input after transition
    useEffect(() => {
        if (open) {
            const id = setTimeout(() => inputRef.current?.focus(), 50);
            return () => clearTimeout(id);
        }
    }, [open]);

    const grouped: { labelKey: string; items: NavItem[] }[] = [
        ...(isPermitted(navigation.home, can)
            ? [{ labelKey: 'shell.nav.home', items: [navigation.home] }]
            : []),
        ...permittedSections(navigation, can).flatMap(
            (section) => section.groups,
        ),
    ];

    function go(href: string) {
        router.visit(href);
        onClose();
    }

    return (
        <Transition show={open} as={Fragment}>
            <Dialog
                as="div"
                className="fixed inset-0 z-50"
                onClose={onClose}
                aria-label={t('shell.nav.command_menu')}
            >
                {/* Backdrop */}
                <TransitionChild
                    as={Fragment}
                    enter="ease-out duration-150"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-100"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div
                        className="absolute inset-0 bg-black/50 backdrop-blur-xs"
                        aria-hidden="true"
                    />
                </TransitionChild>

                {/* Panel */}
                <TransitionChild
                    as={Fragment}
                    enter="ease-out duration-150"
                    enterFrom="opacity-0 scale-95 translate-y-2"
                    enterTo="opacity-100 scale-100 translate-y-0"
                    leave="ease-in duration-100"
                    leaveFrom="opacity-100 scale-100 translate-y-0"
                    leaveTo="opacity-0 scale-95 translate-y-2"
                >
                    <DialogPanel className="relative mx-4 mt-20 w-auto max-w-lg overflow-hidden rounded-panel bg-(--app-surface) shadow-2xl ring-1 ring-(--app-border) sm:mx-auto sm:w-full">
                        <Command
                            className="flex flex-col"
                            shouldFilter={true}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape') onClose();
                            }}
                        >
                            {/* Search input */}
                            <div className="flex items-center gap-2 border-b border-(--app-border) px-4 py-3">
                                <Search
                                    className="h-4 w-4 shrink-0 text-(--app-muted-foreground)"
                                    aria-hidden="true"
                                />
                                <Command.Input
                                    ref={inputRef}
                                    value={search}
                                    onValueChange={setSearch}
                                    placeholder={t(
                                        'shell.nav.type_command_or_search',
                                    )}
                                    className="flex-1 bg-transparent text-sm text-(--app-foreground) outline-hidden placeholder:text-(--app-muted-foreground) focus:outline-hidden focus-visible:outline-hidden"
                                />
                                <kbd className="hidden rounded-sm border border-(--app-border) px-1.5 py-0.5 text-[10px] font-medium text-(--app-muted-foreground) sm:block">
                                    ESC
                                </kbd>
                            </div>

                            {/* Results */}
                            <Command.List className="max-h-80 overflow-y-auto py-2">
                                <Command.Empty className="px-4 py-8 text-center text-sm text-(--app-muted-foreground)">
                                    {t('shell.nav.no_results')}
                                </Command.Empty>

                                {grouped.map((group) => (
                                    <Command.Group
                                        key={group.labelKey}
                                        heading={t(group.labelKey)}
                                        className="mb-1"
                                    >
                                        <div className="mb-1 px-3 pt-2 text-[10px] font-semibold text-(--app-muted-foreground)">
                                            {t(group.labelKey)}
                                        </div>
                                        {group.items.map((entry) => {
                                            const Icon = entry.icon;
                                            return (
                                                <Command.Item
                                                    key={entry.href}
                                                    value={`${t(entry.labelKey)} ${t(group.labelKey)}`}
                                                    onSelect={() =>
                                                        go(entry.href)
                                                    }
                                                    className="flex cursor-pointer items-center gap-3 px-4 py-2 text-sm text-(--app-foreground) data-[selected=true]:bg-primary-light data-[selected=true]:text-primary"
                                                >
                                                    <Icon
                                                        className="h-4 w-4 shrink-0 text-(--app-muted-foreground)"
                                                        aria-hidden="true"
                                                    />
                                                    {t(entry.labelKey)}
                                                </Command.Item>
                                            );
                                        })}
                                    </Command.Group>
                                ))}
                            </Command.List>

                            {/* Footer hint */}
                            <div className="flex items-center justify-end gap-3 border-t border-(--app-border) px-4 py-2">
                                <span className="text-[11px] text-(--app-muted-foreground)">
                                    <kbd className="rounded-sm border border-(--app-border) px-1">
                                        ↑
                                    </kbd>
                                    <kbd className="ml-0.5 rounded-sm border border-(--app-border) px-1">
                                        ↓
                                    </kbd>{' '}
                                    {t('shell.nav.hint_navigate')}
                                </span>
                                <span className="text-[11px] text-(--app-muted-foreground)">
                                    <kbd className="rounded-sm border border-(--app-border) px-1">
                                        ↵
                                    </kbd>{' '}
                                    {t('shell.nav.hint_open')}
                                </span>
                            </div>
                        </Command>
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
