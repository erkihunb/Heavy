import { usePage } from '@inertiajs/react';
import type { TranslationKey } from '@/types/translations';

export type Translations = Record<string, unknown>;
export type Replacements = Record<string, string | number>;

/**
 * Walk the translations along a key's segments. A name in a language file
 * may itself contain a dot (the audit events are keyed "permit.transition",
 * "payment.verified", …), so at each level the shortest run of segments that
 * names something is tried first, then longer runs joined back with dots.
 */
function resolve(node: unknown, segments: string[]): unknown {
    if (segments.length === 0) {
        return node;
    }

    if (node === null || typeof node !== 'object') {
        return undefined;
    }

    const branch = node as Record<string, unknown>;

    for (let taken = 1; taken <= segments.length; taken++) {
        const name = segments.slice(0, taken).join('.');

        if (Object.hasOwn(branch, name)) {
            const found = resolve(branch[name], segments.slice(taken));

            if (found !== undefined) {
                return found;
            }
        }
    }

    return undefined;
}

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
    const line = resolve(translations, key.split('.'));

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
