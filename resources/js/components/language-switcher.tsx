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
                'inline-flex items-center gap-1 rounded-md border border-border p-0.5 text-sm',
                className,
            )}
        >
            <Languages
                aria-hidden="true"
                className="mx-1 size-4 text-muted-foreground"
            />
            {Object.entries(locales).map(([code, label]) => (
                <button
                    key={code}
                    type="button"
                    lang={code}
                    aria-pressed={code === locale}
                    onClick={() => switchTo(code)}
                    className={cn(
                        'min-h-8 rounded px-2.5 py-1 font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        code === locale
                            ? 'bg-primary text-primary-foreground'
                            : 'text-foreground hover:bg-accent',
                    )}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}
