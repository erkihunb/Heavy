import { router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect } from 'react';
import type { ReactNode } from 'react';
import { update } from '@/routes/locale';

export type Locale = 'en' | 'am';

type LocaleContextValue = {
    locale: Locale;
    setLocale: (locale: Locale) => void;
};

const toLocale = (value: unknown): Locale => (value === 'en' ? 'en' : 'am');

/**
 * The interface language. Unlike the source system, Heavy renders the language on the
 * server (the `locale` and `translations` shared props), so switching asks
 * the server: PUT /locale stores the choice (session, cookie and the signed-in
 * person's profile) and the page comes back in the new language.
 */
export const LocaleContext = createContext<LocaleContextValue>({
    locale: 'am',
    setLocale: () => {},
});

export function LocaleProvider({ children }: { children: ReactNode }) {
    const locale = toLocale(usePage().props.locale);

    const setLocale = useCallback(
        (next: Locale) => {
            if (next === locale) return;
            router.put(
                update.url(),
                { locale: next },
                { preserveScroll: true, preserveState: false },
            );
        },
        [locale],
    );

    // Inertia swaps pages without reloading, so <html lang> (fonts, line heights) follows the props here.
    useEffect(() => {
        document.documentElement.lang = locale;
        document.body.classList.toggle('locale-am', locale === 'am');
    }, [locale]);

    return (
        <LocaleContext.Provider value={{ locale, setLocale }}>
            {children}
        </LocaleContext.Provider>
    );
}

export function useLocaleContext(): LocaleContextValue {
    return useContext(LocaleContext);
}
