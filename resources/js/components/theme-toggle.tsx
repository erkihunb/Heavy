import { usePage } from '@inertiajs/react';
import { MoonIcon } from '@solar-icons/react/linear/moon';
import { SunIcon } from '@solar-icons/react/linear/sun';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/lib/i18n';

/**
 * Light / dark switch for the top bar. The full choice (including "follow
 * the device") stays in Settings → Appearance.
 */
export function ThemeToggle({ className }: { className?: string }) {
    const { t } = useTranslation();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const { appearance } = usePage().props;
    const dark = resolvedAppearance === 'dark';

    // Everyone sees the default when an Admin does not let people choose.
    if (appearance && !appearance.allowUserMode) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={() => updateAppearance(dark ? 'light' : 'dark')}
            aria-label={dark ? t('nav.theme_light') : t('nav.theme_dark')}
            title={dark ? t('nav.theme_light') : t('nav.theme_dark')}
            className={className}
        >
            {dark ? (
                <SunIcon className="size-5" aria-hidden="true" />
            ) : (
                <MoonIcon className="size-5" aria-hidden="true" />
            )}
        </button>
    );
}
