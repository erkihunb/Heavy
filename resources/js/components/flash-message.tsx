import { usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** How long a message stays before it fades (EUISIS toasts). */
const VISIBLE_MS = 7000;

/**
 * Shows the one-off message left by the previous request ("Vehicle
 * registered.") as a toast in the corner, announced politely to screen
 * readers. It closes by itself, or with its close button.
 */
export function FlashMessage() {
    const { flash } = usePage().props;
    const { t } = useTranslation();
    const message = flash.success ?? flash.warning;
    // Every visit brings a new flash object, so the same text can show again.
    const [dismissed, setDismissed] = useState<object | null>(null);

    useEffect(() => {
        if (!message) {
            return;
        }

        const timer = window.setTimeout(() => setDismissed(flash), VISIBLE_MS);

        return () => window.clearTimeout(timer);
    }, [message, flash]);

    const shown = message && dismissed !== flash ? message : null;
    const isSuccess = flash.success !== null;
    const Icon = isSuccess ? CircleCheck : CircleAlert;

    return (
        <div
            role="status"
            aria-live="polite"
            className="pointer-events-none fixed right-4 bottom-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2"
        >
            {shown && (
                <p
                    className={cn(
                        'pointer-events-auto flex animate-rise items-start gap-3 rounded-md border bg-card px-4 py-3 text-sm font-medium shadow-lg',
                        isSuccess
                            ? 'border-emerald-200 text-emerald-800 dark:border-emerald-900 dark:text-emerald-300'
                            : 'border-amber-200 text-amber-800 dark:border-amber-900 dark:text-amber-300',
                    )}
                >
                    <Icon
                        aria-hidden="true"
                        className="mt-0.5 size-4 shrink-0"
                    />
                    <span className="flex-1 text-foreground">{shown}</span>
                    <button
                        type="button"
                        onClick={() => setDismissed(flash)}
                        aria-label={t('common.close')}
                        className="-m-1 rounded-xs p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X aria-hidden="true" className="size-4" />
                    </button>
                </p>
            )}
        </div>
    );
}
