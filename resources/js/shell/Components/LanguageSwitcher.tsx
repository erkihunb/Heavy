import { useLocale } from '@/shell/hooks/useLocale';
import { useSystemSettings } from '@/shell/hooks/useSystemSettings';

/**
 * The EN / አማ switch. The page comes back from the server in the
 * chosen language (LocaleContext), and LocaleProvider keeps <html lang> in step.
 */
export default function LanguageSwitcher({
    variant = 'default',
}: {
    variant?: 'default' | 'toolbar';
}) {
    const { locale, setLocale, localeOptions, t } = useLocale();
    const { getBoolean } = useSystemSettings();

    if (!getBoolean('appearance.show_language_switcher', true)) return null;
    if (localeOptions.length <= 1) return null;

    return (
        <div
            className={
                variant === 'toolbar'
                    ? 'flex h-9 shrink-0 items-center gap-0.5 rounded-md bg-(--app-surface-muted) p-0.5'
                    : 'flex items-center gap-0.5 rounded-lg border border-(--app-border) p-0.5'
            }
            role="group"
            aria-label={t('shell.language.label')}
        >
            {localeOptions.map((opt) => (
                <button
                    key={opt.value}
                    type="button"
                    lang={opt.value}
                    onClick={() => setLocale(opt.value)}
                    className={[
                        'rounded-sm text-xs font-semibold transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-hidden',
                        variant === 'toolbar'
                            ? 'flex h-8 min-w-8 items-center justify-center px-1.5'
                            : 'px-2.5 py-1',
                        locale === opt.value
                            ? variant === 'toolbar'
                                ? 'bg-(--app-surface) text-(--app-foreground) shadow-xs'
                                : 'bg-primary text-white'
                            : 'text-(--app-muted-foreground) hover:text-(--app-foreground)',
                    ].join(' ')}
                    aria-pressed={locale === opt.value}
                    aria-label={
                        opt.value === 'am' ? 'አማርኛ (Amharic)' : 'English'
                    }
                >
                    {opt.label}
                </button>
            ))}
        </div>
    );
}
