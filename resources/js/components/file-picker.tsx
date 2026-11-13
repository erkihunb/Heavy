import { FileUp, X } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * A file field in the page's language: the browser's own control reads
 * "Choose File / No file chosen" in the browser's language whatever the page
 * says. The button is the field (the form label points at it); the file input
 * itself stays hidden and is only opened by the button. Screen readers hear
 * the label, then the chosen file (or that there is none) and any hint.
 */
export function FilePicker({
    id,
    accept,
    file,
    onChange,
    describedBy,
    invalid,
    storedName,
}: {
    id: string;
    accept?: string;
    file: File | null;
    onChange: (file: File | null) => void;
    describedBy?: string;
    invalid?: boolean;
    /** A file saved before (on an edit), shown while no new one is chosen. */
    storedName?: string;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const statusId = `${id}-file`;
    const chosen = file?.name ?? storedName ?? null;

    const clear = () => {
        onChange(null);

        if (input.current) {
            input.current.value = '';
        }
    };

    return (
        <div
            className={cn(
                'flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md border bg-background px-3 py-2',
                invalid ? 'border-destructive' : 'border-input',
            )}
        >
            <input
                ref={input}
                type="file"
                accept={accept}
                tabIndex={-1}
                aria-hidden="true"
                className="sr-only"
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
            />
            <Button
                id={id}
                type="button"
                variant="outline"
                size="sm"
                aria-describedby={[statusId, describedBy]
                    .filter(Boolean)
                    .join(' ')}
                aria-invalid={invalid || undefined}
                onClick={() => input.current?.click()}
            >
                <FileUp aria-hidden="true" />
                {chosen ? t('common.file.change') : t('common.file.choose')}
            </Button>
            <span
                id={statusId}
                className={cn(
                    'min-w-0 flex-1 truncate text-sm',
                    chosen ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {chosen ?? t('common.file.none')}
            </span>
            {file && (
                <Button type="button" variant="ghost" size="sm" onClick={clear}>
                    <X aria-hidden="true" />
                    {t('common.file.remove')}
                </Button>
            )}
        </div>
    );
}
