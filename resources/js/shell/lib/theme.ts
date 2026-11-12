/*
 * Light / dark mode is Heavy's own (hooks/use-appearance: localStorage
 * `appearance-mode`, the `appearance_mode` cookie and the Admin default on
 * <html data-default-appearance>), so the admin shell and the older pages
 * always agree on the mode. These types name its values the source kit's way.
 */
export type {
    Appearance as ThemePreference,
    ResolvedAppearance as Theme,
} from '@/hooks/use-appearance';

/*
 * Interface style (System settings → Appearance → Interface style). The admin
 * choice is the default; when people may choose for themselves, a person may
 * pick their own, kept in localStorage like the light/dark preference.
 * ThemeStyle (PHP) writes the admin choice on <html data-ui-style> for the
 * first paint.
 */
export type UiStyle = 'swiss' | 'classic' | 'soft' | 'contrast';
export const UI_STYLES: readonly UiStyle[] = [
    'swiss',
    'classic',
    'soft',
    'contrast',
];
const UI_STYLE_KEY = 'ui-style';

export function isUiStyle(value: unknown): value is UiStyle {
    return (
        typeof value === 'string' &&
        (UI_STYLES as readonly string[]).includes(value)
    );
}

export function getStoredUiStyle(): UiStyle | null {
    if (typeof window === 'undefined') return null;
    try {
        const stored = localStorage.getItem(UI_STYLE_KEY);
        return isUiStyle(stored) ? stored : null;
    } catch {
        return null;
    }
}

export function applyUiStyle(style: UiStyle): void {
    if (typeof document === 'undefined') return;
    document.documentElement.dataset.uiStyle = style;
}

/** The style to show: the person's own when switching is allowed, else the organisation's. */
export function resolveUiStyle(
    adminStyle: unknown,
    allowUserSwitching: boolean,
): UiStyle {
    const fallback: UiStyle = isUiStyle(adminStyle) ? adminStyle : 'swiss';
    return (allowUserSwitching ? getStoredUiStyle() : null) ?? fallback;
}

/** `null` clears the personal choice and goes back to the organisation default. */
export function setUiStylePreference(
    style: UiStyle | null,
    adminStyle: unknown,
): void {
    if (typeof window !== 'undefined') {
        try {
            if (style) localStorage.setItem(UI_STYLE_KEY, style);
            else localStorage.removeItem(UI_STYLE_KEY);
        } catch {
            // Storage unavailable: the choice still applies for this visit.
        }
    }
    applyUiStyle(style ?? (isUiStyle(adminStyle) ? adminStyle : 'swiss'));
}
