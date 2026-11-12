import { usePage } from '@inertiajs/react';
import { createContext, useContext } from 'react';
import {
    getBooleanSetting,
    getNumberSetting,
    getSetting,
    getStringArraySetting,
    getStringSetting,
} from '@/shell/lib/settings';
import type { PublicSettings } from '@/shell/lib/settings';

/** Lets a settings page preview unsaved values in the shell. */
export const SystemSettingsPreviewContext =
    createContext<PublicSettings | null>(null);

export function useSystemSettings() {
    const page = usePage();
    const overrides = useContext(SystemSettingsPreviewContext);
    const settings: PublicSettings = { ...page.props.settings, ...overrides };

    return {
        settings,
        get: <T>(key: string, fallback: T): T =>
            getSetting(settings, key, fallback),
        getString: (key: string, fallback = ''): string =>
            getStringSetting(settings, key, fallback),
        getBoolean: (key: string, fallback = false): boolean =>
            getBooleanSetting(settings, key, fallback),
        getNumber: (key: string, fallback = 0): number =>
            getNumberSetting(settings, key, fallback),
        getStringArray: (key: string, fallback: string[] = []): string[] =>
            getStringArraySetting(settings, key, fallback),
    };
}
