import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Button,
    FieldError,
    FormField,
    Input,
    Select,
} from '@/shell/Components/ui';
import UserAvatar from '@/shell/Components/UserAvatar';
import { useLocale } from '@/shell/hooks/useLocale';

export const GENDERS = ['male', 'female'] as const;

/** "+251911234567" or "0911234567" → "911234567", for the field after the +251 prefix. */
export function localPhone(phone: string | null | undefined): string {
    const digits = (phone ?? '').replace(/[^\d]/g, '');
    if (digits.startsWith('251')) return digits.slice(3);
    if (digits.startsWith('0')) return digits.slice(1);
    return digits;
}

/** The nine digits typed after the prefix, sent as an international number. */
export function internationalPhone(local: string): string {
    const digits = local.replace(/[^\d]/g, '');
    return digits === '' ? '' : `+251${localPhone(digits)}`;
}

/** Digits in groups of four, as printed on the Fayda card. */
function groupNationalId(value: string): string {
    return value
        .replace(/[^\d]/g, '')
        .slice(0, 16)
        .replace(/(\d{4})(?=\d)/g, '$1 ');
}

/**
 * The photo of a member of staff, with a preview of the chosen file. Removing
 * a stored photo is only asked for when `onRemove` is given (the edit page).
 * `stacked` puts the button under the picture, for a column beside the form.
 */
export function AvatarPicker({
    name,
    file,
    current,
    removed = false,
    error,
    disabled = false,
    stacked = false,
    onChange,
    onRemove,
}: {
    name: string;
    file: File | null;
    current?: string | null;
    removed?: boolean;
    error?: string;
    disabled?: boolean;
    stacked?: boolean;
    onChange: (file: File | null) => void;
    onRemove?: (remove: boolean) => void;
}) {
    const { t } = useLocale();
    const inputId = useId();
    const input = useRef<HTMLInputElement>(null);
    const preview = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    useEffect(
        () => () => {
            if (preview) URL.revokeObjectURL(preview);
        },
        [preview],
    );

    const shown = preview ?? (removed ? null : (current ?? null));

    return (
        <div
            className={
                stacked
                    ? 'flex flex-col items-center gap-3 text-center'
                    : 'flex items-center gap-4'
            }
        >
            <UserAvatar
                src={shown}
                name={name.trim() === '' ? '?' : name}
                size={stacked ? 96 : 72}
                className="shrink-0 ring-1 ring-(--app-border)"
            />
            <div
                className={
                    stacked
                        ? 'flex flex-col items-center gap-1.5'
                        : 'min-w-0 space-y-1.5'
                }
            >
                {!stacked && (
                    <span className="block text-sm font-medium text-(--app-foreground)">
                        {t('rbac.users.fields.photo')}
                    </span>
                )}
                {!disabled && (
                    <div
                        className={
                            stacked
                                ? 'flex flex-wrap justify-center gap-2'
                                : 'flex flex-wrap gap-2'
                        }
                    >
                        <input
                            ref={input}
                            id={inputId}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="sr-only"
                            aria-label={t('rbac.users.fields.photo')}
                            aria-invalid={error ? true : undefined}
                            onChange={(event) => {
                                onChange(event.target.files?.[0] ?? null);
                                onRemove?.(false);
                                event.target.value = '';
                            }}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => input.current?.click()}
                        >
                            {shown
                                ? t('rbac.users.changePhoto')
                                : t('rbac.users.choosePhoto')}
                        </Button>
                        {file && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onChange(null)}
                            >
                                {current && !removed
                                    ? t('rbac.users.keepPhoto')
                                    : t('rbac.users.removePhoto')}
                            </Button>
                        )}
                        {!file && current && onRemove && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onRemove(!removed)}
                            >
                                {removed
                                    ? t('rbac.users.keepPhoto')
                                    : t('rbac.users.removePhoto')}
                            </Button>
                        )}
                    </div>
                )}
                <p className="max-w-56 text-xs text-(--app-muted-foreground)">
                    {removed && !file
                        ? t('rbac.users.photoRemoved')
                        : t('rbac.users.photoHelp')}
                </p>
                <FieldError>{error}</FieldError>
            </div>
        </div>
    );
}

/** An Ethiopian number: the +251 prefix is shown, the person types the rest. */
export function PhoneField({
    value,
    onChange,
    error,
    disabled = false,
}: {
    value: string;
    onChange: (local: string) => void;
    error?: string;
    disabled?: boolean;
}) {
    const { t } = useLocale();

    return (
        <FormField label={t('rbac.users.fields.phone')} error={error}>
            {({ id, describedBy, invalid }) => (
                <div className="flex">
                    <span className="inline-flex items-center rounded-l-control border border-r-0 border-(--app-border-strong) bg-(--app-surface-muted) px-3 text-sm text-(--app-muted-foreground)">
                        +251
                    </span>
                    <Input
                        id={id}
                        type="tel"
                        inputMode="numeric"
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        disabled={disabled}
                        autoComplete="off"
                        maxLength={9}
                        placeholder={t('rbac.users.phonePlaceholder')}
                        className="rounded-l-none"
                        value={value}
                        onChange={(event) =>
                            onChange(localPhone(event.target.value).slice(0, 9))
                        }
                    />
                </div>
            )}
        </FormField>
    );
}

export function NationalIdField({
    value,
    onChange,
    error,
    disabled = false,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
    disabled?: boolean;
}) {
    const { t } = useLocale();

    return (
        <FormField
            label={t('rbac.users.fields.nationalId')}
            error={error}
            description={t('rbac.users.nationalIdHelp')}
        >
            {({ id, describedBy, invalid }) => (
                <Input
                    id={id}
                    inputMode="numeric"
                    aria-describedby={describedBy}
                    aria-invalid={invalid}
                    disabled={disabled}
                    autoComplete="off"
                    placeholder={t('rbac.users.nationalIdPlaceholder')}
                    className="font-mono tracking-wide"
                    value={groupNationalId(value)}
                    onChange={(event) =>
                        onChange(
                            event.target.value
                                .replace(/[^\d]/g, '')
                                .slice(0, 16),
                        )
                    }
                />
            )}
        </FormField>
    );
}

export function GenderField({
    value,
    onChange,
    error,
    disabled = false,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
    disabled?: boolean;
}) {
    const { t } = useLocale();

    return (
        <FormField label={t('rbac.users.fields.gender')} error={error}>
            {({ id, describedBy, invalid }) => (
                <Select
                    id={id}
                    aria-describedby={describedBy}
                    aria-invalid={invalid}
                    disabled={disabled}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                >
                    <option value="">
                        {t('rbac.users.genderPlaceholder')}
                    </option>
                    {GENDERS.map((gender) => (
                        <option key={gender} value={gender}>
                            {t(`rbac.users.genders.${gender}`)}
                        </option>
                    ))}
                </Select>
            )}
        </FormField>
    );
}

/** A password box whose label row carries a show/hide switch. */
export function PasswordField({
    label,
    value,
    onChange,
    error,
    description,
    placeholder,
    required = false,
    toggle = false,
    onGenerate,
}: {
    label: ReactNode;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    description?: ReactNode;
    placeholder?: string;
    required?: boolean;
    toggle?: boolean;
    /** Fills the box with a new random password; it is then shown so it can be handed over. */
    onGenerate?: () => void;
}) {
    const { t } = useLocale();
    const [shown, setShown] = useState(false);

    return (
        <div className="relative">
            {(toggle || onGenerate) && (
                <span className="absolute -top-2.5 right-0 flex items-center gap-1">
                    {onGenerate && (
                        <button
                            type="button"
                            className="inline-flex min-h-9 items-center rounded-control px-1.5 text-xs font-medium text-primary hover:underline pointer-coarse:min-h-11"
                            onClick={() => {
                                onGenerate();
                                setShown(true);
                            }}
                        >
                            {t('rbac.users.create.generatePassword')}
                        </button>
                    )}
                    {toggle && (
                        <button
                            type="button"
                            className="inline-flex min-h-9 items-center rounded-control px-1.5 text-xs font-medium text-primary hover:underline pointer-coarse:min-h-11"
                            aria-pressed={shown}
                            onClick={() => setShown((value) => !value)}
                        >
                            {shown
                                ? t('rbac.users.hidePassword')
                                : t('rbac.users.showPassword')}
                        </button>
                    )}
                </span>
            )}
            <FormField
                label={label}
                required={required}
                error={error}
                description={description}
            >
                {({ id, describedBy, invalid }) => (
                    <Input
                        id={id}
                        type={shown ? 'text' : 'password'}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        autoComplete="new-password"
                        placeholder={placeholder}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                    />
                )}
            </FormField>
        </div>
    );
}
