import { usePage } from '@inertiajs/react';
import { useLocale } from '@/shell/hooks/useLocale';
import { useSystemSettings } from '@/shell/hooks/useSystemSettings';
import { BRANDING_FALLBACK, brandNameFor } from '@/shell/lib/branding';
import type { Branding } from '@/shell/lib/branding';

export { BRANDING_FALLBACK, type Branding };

/**
 * The product's identity for the shell: live settings → the translations in
 * lang/{en,am}/common.php → the fallbacks in shell/lib/branding.ts. The logo
 * is the one uploaded in System settings → General (`branding.logo`).
 */
export function useBranding() {
    const { props } = usePage();
    const { settings } = useSystemSettings();
    const { locale, t } = useLocale();

    // A key the dictionary lacks comes back unchanged; fall back then.
    const line = (key: string, fallback: string): string => {
        const value = t(key);
        return value === key ? fallback : value;
    };

    const brandName = line('common.app_name', brandNameFor(locale, settings));
    const brandNameEn = brandNameFor('en', settings);
    const brandNameAm = brandNameFor('am', settings);
    const shortName = line(
        'common.authority_short',
        BRANDING_FALLBACK.shortName,
    );
    const officialName = line(
        'common.authority',
        locale === 'am'
            ? BRANDING_FALLBACK.officialNameAm
            : BRANDING_FALLBACK.officialNameEn,
    );

    return {
        /** The brand in the current language. */
        brandName,
        brandNameEn,
        brandNameAm,
        shortName,
        /** The issuing authority in the current language. */
        officialName,
        logoUrl: props.branding?.logo ?? null,
        faviconUrl: props.branding?.favicon ?? null,
    };
}
