import { usePage } from '@inertiajs/react';
import { getBooleanSetting, getStringSetting } from '@/shell/lib/settings';
import type { PublicSettings } from '@/shell/lib/settings';

export function useSystemSettings() {
    const settings: PublicSettings = { ...usePage().props.settings };

    return {
        settings,
        getString: (key: string, fallback = ''): string =>
            getStringSetting(settings, key, fallback),
        getBoolean: (key: string, fallback = false): boolean =>
            getBooleanSetting(settings, key, fallback),
    };
}
