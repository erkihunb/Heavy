import { CircleCheck, RotateCcw, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * The bar at the foot of a settings section (as in EUISIS): it sticks to the
 * bottom of the screen while there are unsaved changes, with Discard and
 * Save; otherwise it says everything is saved. Ctrl+S (⌘S) also saves.
 */
export function SettingsSaveBar({
    dirty,
    processing,
    onDiscard,
}: {
    dirty: boolean;
    processing: boolean;
    onDiscard: () => void;
}) {
    const { t } = useTranslation();

    return (
        <div
            className={cn(
                'sticky bottom-0 z-10 -mx-1 flex flex-wrap items-center justify-between gap-3 rounded-card border px-4 py-3 transition-colors',
                dirty
                    ? 'border-primary bg-card shadow-lg'
                    : 'border-border bg-muted/60',
            )}
        >
            <p
                aria-live="polite"
                className={cn(
                    'flex items-center gap-2 text-sm font-medium',
                    !dirty && 'text-muted-foreground',
                )}
            >
                {dirty ? (
                    <>
                        <span
                            aria-hidden="true"
                            className="size-2 rounded-full bg-primary"
                        />
                        {t('admin.settings.unsaved.changes')}
                        <span className="hidden text-xs font-normal text-muted-foreground sm:inline">
                            · {t('admin.settings.unsaved.shortcut')}
                        </span>
                    </>
                ) : (
                    <>
                        <CircleCheck aria-hidden="true" className="size-4" />
                        {t('admin.settings.unsaved.all_saved')}
                    </>
                )}
            </p>
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    disabled={!dirty || processing}
                    onClick={onDiscard}
                >
                    <RotateCcw aria-hidden="true" />
                    {t('admin.settings.unsaved.discard')}
                </Button>
                <Button type="submit" disabled={!dirty || processing}>
                    {processing ? <Spinner /> : <Save aria-hidden="true" />}
                    {t('admin.settings.save')}
                </Button>
            </div>
        </div>
    );
}
