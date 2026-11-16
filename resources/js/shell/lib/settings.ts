/**
 * The flat settings map the shell reads (`settings` shared prop, built by
 * App\Http\Middleware\HandleInertiaRequests from config/permits.php), keyed
 * as in the source kit: 'appearance.primary_color', 'appearance.show_breadcrumbs', …
 */
export type PublicSettings = Record<string, unknown>;

export function getStringSetting(
    settings: PublicSettings | undefined,
    key: string,
    fallback = '',
): string {
    const value = settings?.[key];

    return typeof value === 'string' ? value : fallback;
}

export function getBooleanSetting(
    settings: PublicSettings | undefined,
    key: string,
    fallback = false,
): boolean {
    const value = settings?.[key];

    return typeof value === 'boolean' ? value : fallback;
}
