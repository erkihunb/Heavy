import { usePage } from '@inertiajs/react';
import type { TranslationKey } from '@/types/translations';

export type Translations = Record<string, unknown>;
export type Replacements = Record<string, string | number>;

/**
 * Look up a dot-notation key ("common.language") in the translations sent by
 * the server and substitute Laravel-style ":name" placeholders. A missing key
 * is returned as-is so the gap is visible instead of rendering nothing.
 */
export function translate(
    translations: Translations,
    key: string,
    replacements: Replacements = {},
): string {
    const line = key
        .split('.')
        .reduce<unknown>(
            (carry, segment) =>
                carry !== null && typeof carry === 'object'
                    ? (carry as Record<string, unknown>)[segment]
                    : undefined,
            translations,
        );

    if (typeof line !== 'string') {
        return key;
    }

    // Longest names first, so ":names" is not clobbered by ":name".
    return Object.keys(replacements)
        .sort((a, b) => b.length - a.length)
        .reduce(
            (text, name) =>
                text.replaceAll(`:${name}`, String(replacements[name])),
            line,
        );
}

/**
 * Typed translator bound to the current page's locale. Keys are checked at
 * compile time against the generated `TranslationKey` type.
 */
export function useTranslation() {
    const { translations, locale, locales } = usePage().props;

    const t = (key: TranslationKey, replacements?: Replacements): string =>
        translate(translations, key, replacements);

    return { t, locale, locales };
}
