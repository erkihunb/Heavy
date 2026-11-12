/**
 * The product's name for the shell. Components read it through useBranding();
 * shell translations may write `{brand}` and useLocale().t fills it in.
 *
 * The names themselves live in lang/{en,am}/common.php (`app_name`,
 * `authority`, `authority_short`); these values are only the fallback for a
 * page rendered without translations.
 */
export type Branding = {
    brandNameEn: string;
    brandNameAm: string;
    shortName: string;
    officialNameEn: string;
    officialNameAm: string;
    logoUrl: string | null;
    faviconUrl: string | null;
};

export const BRANDING_FALLBACK: Branding = {
    brandNameEn: 'Heavy Vehicle Movement Permits',
    brandNameAm: 'የከባድ ተሽከርካሪዎች መንቀሳቀሻ ፍቃድ',
    shortName: 'Roads Authority',
    officialNameEn: 'Addis Ababa City Roads Authority',
    officialNameAm: 'የአዲስ አበባ ከተማ መንገዶች ባለስልጣን',
    logoUrl: null,
    faviconUrl: null,
};

const text = (value: unknown): string | undefined =>
    typeof value === 'string' ? value.trim() : undefined;

/**
 * The brand in a language: live settings ('app.name', 'app.name_am') win over
 * the fallback; a blank value counts as unset.
 */
export function brandNameFor(
    locale: string,
    settings: Record<string, unknown> = {},
): string {
    if (locale === 'am') {
        return text(settings['app.name_am']) || BRANDING_FALLBACK.brandNameAm;
    }

    return text(settings['app.name']) || BRANDING_FALLBACK.brandNameEn;
}
