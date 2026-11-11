import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { update } from '@/routes/locale';

/**
 * Lets anyone — guest or signed-in — choose the interface language. Each
 * language is labelled in its own script so it is recognisable regardless of
 * the language currently shown.
 */
export function LanguageSwitcher({ className }: { className?: string }) {
    const { t, locale, locales } = useTranslation();

    const switchTo = (code: string) => {
        if (code === locale) {
            return;
        }

        router.put(
            update.url(),
            { locale: code },
            { preserveScroll: true, preserveState: false },
        );
    };

    return (
        <div
            role="group"
            aria-label={t('common.language')}
            className={cn(
                'inline-flex h-9 items-center gap-0.5 rounded-md border border-border bg-card p-0.5 text-sm',
                className,
            )}
        >
            <Languages
                aria-hidden="true"
                className="mx-1 hidden size-4 text-muted-foreground sm:block"
            />
            {Object.entries(locales).map(([code, label]) => (
                <button
                    key={code}
                    type="button"
                    lang={code}
                    aria-pressed={code === locale}
                    onClick={() => switchTo(code)}
                    className={cn(
                        'h-full rounded-xs px-2.5 text-xs font-medium transition-colors duration-150',
                        code === locale
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    )}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}
