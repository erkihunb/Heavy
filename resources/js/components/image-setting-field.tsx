import { ImageUp, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * An uploaded image setting (EUISIS ImageSettingField): the current image,
 * Choose / Replace and Remove, and the chosen file until the form is saved.
 */
export function ImageSettingField({
    id,
    label,
    hint,
    error,
    url,
    configured,
    accept,
    file,
    markedForRemoval,
    onFileChange,
    onRemoveChange,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    url?: string;
    configured: boolean;
    accept: string;
    file: File | null;
    markedForRemoval: boolean;
    onFileChange: (file: File | null) => void;
    onRemoveChange: (remove: boolean) => void;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

    // The object URL of the chosen file lives as long as the choice does.
    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const choose = (event: ChangeEvent<HTMLInputElement>) => {
        const next = event.target.files?.[0] ?? null;
        setPreview(next ? URL.createObjectURL(next) : null);
        onRemoveChange(false);
        onFileChange(next);
    };

    const discard = () => {
        setPreview(null);
        onFileChange(null);

        if (input.current) {
            input.current.value = '';
        }
    };

    const shown = preview ?? (markedForRemoval ? null : url);
    const hintId = hint ? `${id}-hint` : undefined;
    const formats = accept
        .split(',')
        .map((type) => type.replace(/^\./, '').toUpperCase())
        .join(', ');

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <label htmlFor={id} className="text-sm font-medium">
                    {label}
                </label>
                <span
                    className={cn(
                        'rounded-xs px-1.5 py-0.5 text-xs font-medium',
                        configured
                            ? 'bg-success/10 text-success'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    {configured
                        ? t('admin.settings.image.set')
                        : t('admin.settings.image.unset')}
                </span>
            </div>

            <div className="flex items-center gap-4">
                <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-card border border-dashed border-border-strong bg-muted p-1.5">
                    {shown ? (
                        <img
                            src={shown}
                            alt={t('admin.settings.image.current', {
                                name: label,
                            })}
                            className="size-full object-contain"
                        />
                    ) : (
                        <ImageUp
                            aria-hidden="true"
                            className="size-5 text-muted-foreground"
                        />
                    )}
                </div>

                <div className="min-w-0 flex-1 space-y-2">
                    <input
                        ref={input}
                        id={id}
                        type="file"
                        accept={accept}
                        onChange={choose}
                        className="sr-only"
                        aria-describedby={hintId}
                        aria-invalid={error ? true : undefined}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => input.current?.click()}
                        >
                            {configured || file
                                ? t('admin.settings.image.replace')
                                : t('admin.settings.image.choose')}
                        </Button>
                        {configured && !file && !markedForRemoval && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onRemoveChange(true)}
                            >
                                {t('admin.settings.image.remove')}
                            </Button>
                        )}
                    </div>

                    {markedForRemoval ? (
                        <p className="flex flex-wrap items-center gap-2 text-xs">
                            <span className="rounded-xs bg-warning/15 px-1.5 py-0.5 font-medium text-warning">
                                {t('admin.settings.image.removed_on_save')}
                            </span>
                            <button
                                type="button"
                                onClick={() => onRemoveChange(false)}
                                className="font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                            >
                                {t('admin.settings.image.keep')}
                            </button>
                        </p>
                    ) : file ? (
                        <p className="flex min-w-0 items-center gap-2 text-xs">
                            <span className="shrink-0 rounded-xs bg-primary/10 px-1.5 py-0.5 font-medium text-primary">
                                {t('admin.settings.image.pending')}
                            </span>
                            <span className="min-w-0 truncate">
                                {file.name}
                            </span>
                            <button
                                type="button"
                                onClick={discard}
                                aria-label={t('admin.settings.image.discard')}
                                title={t('admin.settings.image.discard')}
                                className="shrink-0 rounded-xs p-0.5 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                            >
                                <X aria-hidden="true" className="size-3.5" />
                            </button>
                        </p>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            {formats}
                        </p>
                    )}
                </div>
            </div>

            {hint && (
                <p id={hintId} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
