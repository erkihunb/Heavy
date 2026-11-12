import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';
import { translate } from '@/lib/i18n';
import { useLocaleContext } from '@/shell/contexts/LocaleContext';
import type { Locale } from '@/shell/contexts/LocaleContext';
import { brandNameFor } from '@/shell/lib/branding';

/**
 * Locale + translator for the shell, with the source kit's API
 * (`{ locale, setLocale, t, localeOptions }`). The dictionary is the one the
 * server shares (`translations`, from lang/{en,am}/*.php); the shell's own
 * lines are under `shell.*` (lang/{en,am}/shell.php). A missing key comes
 * back as the key itself, as in the source kit.
 */
export function useLocale() {
    const { setLocale } = useLocaleContext();
    const { props } = usePage();
    const locale: Locale = props.locale === 'en' ? 'en' : 'am';
    const translations = props.translations;
    const settings = props.settings ?? {};

    // Shell lines may name the product as `{brand}`.
    const brand = brandNameFor(locale, settings);
    const t = useCallback(
        (key: string): string => {
            const text = translate(translations, key);
            return text.includes('{brand}')
                ? text.replace(/\{brand\}/g, brand)
                : text;
        },
        [translations, brand],
    );

    const localeOptions = useMemo(
        () =>
            Object.keys(props.locales ?? { en: 'English', am: 'አማርኛ' })
                .filter(
                    (code): code is Locale => code === 'en' || code === 'am',
                )
                .map((code) => ({
                    value: code,
                    label: code === 'am' ? 'አማ' : 'EN',
                })),
        [props.locales],
    );

    return { locale, setLocale, t, localeOptions };
}

/**
 * Locale + translator without the locale options (`useTranslator`).
 * In the source kit it worked outside Inertia pages; here every caller is inside one,
 * so it is the same translator as useLocale().
 */
export function useTranslator() {
    const { locale, setLocale, t } = useLocale();

    return { locale, setLocale, t };
}
