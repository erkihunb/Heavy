import { router } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useNavGroups } from '@/hooks/use-nav-groups';
import { useTranslation } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';

/**
 * Search the navigation (EUISIS command menu): Ctrl/⌘+K or the search box in
 * the top bar opens it; arrows move, Enter opens the page.
 */
export function NavSearch({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const groups = useNavGroups();
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const listRef = useRef<HTMLUListElement>(null);

    const results = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase();

        return groups.flatMap((group) =>
            group.items
                .filter(
                    (item) =>
                        needle === '' ||
                        item.title.toLocaleLowerCase().includes(needle) ||
                        (group.label ?? '')
                            .toLocaleLowerCase()
                            .includes(needle),
                )
                .map((item) => ({ ...item, group: group.label })),
        );
    }, [groups, query]);

    /* Closing starts the next search afresh. */
    const change = (next: boolean) => {
        if (!next) {
            setQuery('');
            setActive(0);
        }

        onOpenChange(next);
    };

    const go = (index: number) => {
        const item = results[index];

        if (item) {
            change(false);
            router.visit(toUrl(item.href));
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(index + 1, results.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            go(active);
        }
    };

    return (
        <Dialog open={open} onOpenChange={change}>
            <DialogContent className="top-[15%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-lg">
                <DialogTitle className="sr-only">{t('nav.search')}</DialogTitle>
                <DialogDescription className="sr-only">
                    {t('nav.search_hint')}
                </DialogDescription>
                <input
                    autoFocus
                    type="search"
                    role="combobox"
                    aria-expanded="true"
                    aria-controls="nav-search-results"
                    aria-activedescendant={
                        results[active] ? `nav-result-${active}` : undefined
                    }
                    value={query}
                    placeholder={t('nav.search_placeholder')}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActive(0);
                    }}
                    onKeyDown={onKeyDown}
                    className="h-12 w-full border-b border-border bg-transparent px-4 text-sm outline-none placeholder:text-muted-foreground"
                />
                <ul
                    id="nav-search-results"
                    ref={listRef}
                    role="listbox"
                    className="max-h-80 overflow-y-auto p-1.5"
                >
                    {results.length === 0 ? (
                        <li className="px-3 py-6 text-center text-sm text-muted-foreground">
                            {t('nav.search_empty')}
                        </li>
                    ) : (
                        results.map((item, index) => (
                            <li
                                key={`${item.group}-${item.title}`}
                                id={`nav-result-${index}`}
                                role="option"
                                aria-selected={index === active}
                                onMouseEnter={() => setActive(index)}
                                onClick={() => go(index)}
                                className={cn(
                                    'flex min-h-10 cursor-pointer items-center gap-3 rounded-md px-3 text-sm',
                                    index === active && 'bg-muted',
                                )}
                            >
                                {item.icon && (
                                    <item.icon className="size-[18px] shrink-0 text-muted-foreground" />
                                )}
                                <span className="flex-1 font-medium">
                                    {item.title}
                                </span>
                                {item.group && (
                                    <span className="text-xs text-muted-foreground">
                                        {item.group}
                                    </span>
                                )}
                            </li>
                        ))
                    )}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
