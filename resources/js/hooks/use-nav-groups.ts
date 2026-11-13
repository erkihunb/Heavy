import { usePage } from '@inertiajs/react';
import { navigation } from '@/config/navigation';
import type { NavItem as MenuItem } from '@/config/navigation';
import { useTranslation } from '@/lib/i18n';
import {
    activeItem,
    isPermitted,
    permittedSections,
} from '@/shell/lib/navigation';
import type { NavItem } from '@/types';
import type { TranslationKey } from '@/types/translations';

export type NavGroup = {
    /** Group label; the first group (home) has none. */
    label: string | null;
    items: (NavItem & { isActive: boolean })[];
};

/**
 * The signed-in menu for pages not yet on the admin shell, read from the
 * same list as the shell sidebar (config/navigation.ts) so every page shows
 * the same entries, in the same order, filtered by the same permissions.
 * Each shell group becomes one labelled block. Exactly one entry is marked
 * current, by the shell's own matching rule. Hiding is a convenience only:
 * every route is authorised on the server.
 */
export function useNavGroups(): NavGroup[] {
    const page = usePage();
    const { t } = useTranslation();
    const granted = new Set(page.props.auth.permissions ?? []);
    const can = (permission: string) => granted.has(permission);

    const sections = permittedSections(navigation, can);
    const groups = sections.flatMap((section) => section.groups);
    const current = activeItem(
        [navigation.home, ...groups.flatMap((group) => group.items)],
        page.url,
    );

    const toItem = (item: MenuItem) => ({
        title: t(item.labelKey as TranslationKey),
        href: item.href,
        icon: item.icon,
        isActive: item === current,
    });

    return [
        ...(isPermitted(navigation.home, can)
            ? [{ label: null, items: [toItem(navigation.home)] }]
            : []),
        ...groups.map((group) => ({
            label: t(group.labelKey as TranslationKey),
            items: group.items.map(toItem),
        })),
    ];
}
