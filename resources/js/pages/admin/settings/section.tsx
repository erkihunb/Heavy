import { router, useForm } from '@inertiajs/react';
import { Save, Send, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AdminSettingsLayout from '@/layouts/admin-settings-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { test, update } from '@/routes/admin/settings';
import type { TranslationKey } from '@/types/translations';

/** Mirrors App\Http\Controllers\Admin\SettingsController::show(). */
type Field = {
    key: string;
    field: string;
    group: string;
    type:
        | 'text'
        | 'longtext'
        | 'int'
        | 'bool'
        | 'choice'
        | 'email'
        | 'url'
        | 'time'
        | 'timezone'
        | 'secret'
        | 'color';
    required: boolean;
    min?: number;
    max?: number;
    options?: string[];
    value?: string | number | boolean;
    has_value?: boolean;
};

type Props = {
    section: string;
    sections: string[];
    fields: Field[];
    saved: string[];
    timezones: string[];
};

type FormValue = string | boolean;

const TESTABLE = ['email', 'sms', 'telegram'];

const THEME_SWATCH: Record<string, string> = {
    navy: '#122170',
    forest: '#166534',
    sunset: '#b4410f',
    graphite: '#27272a',
};

const isPlaceholder = (value: FormValue): boolean =>
    typeof value === 'string' &&
    (value.includes('[PLACEHOLDER]') || value.includes('[ጊዜያዊ ጽሑፍ]'));

export default function SettingsSection({
    section,
    sections,
    fields,
    saved,
    timezones,
}: Props) {
    const { t } = useTranslation();
    const optional = (key: string): string | undefined => {
        const text = t(key as TranslationKey);

        return text === key ? undefined : text;
    };

    const initial: Record<string, FormValue> = {};

    for (const field of fields) {
        if (field.type === 'secret') {
            initial[field.field] = '';
            initial[`${field.field}__clear`] = false;
        } else if (field.type === 'bool') {
            initial[field.field] = Boolean(field.value);
        } else {
            initial[field.field] = String(field.value ?? '');
        }
    }

    const form = useForm<Record<string, FormValue>>(initial);
    const [phone, setPhone] = useState('');
    const [testing, setTesting] = useState(false);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url({ section }), { preserveScroll: true });
    };

    const sendTest = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.post(test.url({ section }), section === 'sms' ? { phone } : {}, {
            preserveScroll: true,
            onStart: () => setTesting(true),
            onFinish: () => setTesting(false),
        });
    };

    const groups = Array.from(new Set(fields.map((field) => field.group)));

    const control = (field: Field) => {
        const label = t(
            `admin.settings.fields.${field.field}` as TranslationKey,
        );
        const hint = optional(`admin.settings.hints.${field.field}`);
        const error = form.errors[field.field];
        const value = form.data[field.field];
        const changed = saved.includes(field.field) && (
            <span className="rounded-md bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground">
                {t('admin.settings.changed')}
            </span>
        );

        if (field.type === 'bool') {
            return (
                <div className="flex items-start gap-3">
                    <Checkbox
                        id={field.field}
                        checked={value === true}
                        aria-describedby={
                            hint ? `${field.field}-hint` : undefined
                        }
                        onCheckedChange={(checked) =>
                            form.setData(field.field, checked === true)
                        }
                    />
                    <div className="grid gap-1">
                        <Label
                            htmlFor={field.field}
                            className="flex flex-wrap items-center gap-2"
                        >
                            {label}
                            {changed}
                        </Label>
                        {hint && (
                            <p
                                id={`${field.field}-hint`}
                                className="text-sm text-muted-foreground"
                            >
                                {hint}
                            </p>
                        )}
                        <InputError message={error} />
                    </div>
                </div>
            );
        }

        if (field.type === 'secret') {
            const hintText = field.has_value
                ? t('admin.settings.secret_set')
                : t('admin.settings.secret_unset');

            return (
                <div className="space-y-2">
                    <FormField
                        id={field.field}
                        label={label}
                        hint={[
                            optional(`admin.settings.hints.${field.field}`),
                            hintText,
                        ]
                            .filter(Boolean)
                            .join(' ')}
                        error={error}
                    >
                        {(props) => (
                            <Input
                                {...props}
                                type="password"
                                autoComplete="new-password"
                                translate="no"
                                value={String(value)}
                                disabled={
                                    form.data[`${field.field}__clear`] === true
                                }
                                onChange={(event) =>
                                    form.setData(
                                        field.field,
                                        event.target.value,
                                    )
                                }
                            />
                        )}
                    </FormField>
                    {field.has_value && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id={`${field.field}__clear`}
                                checked={
                                    form.data[`${field.field}__clear`] === true
                                }
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        `${field.field}__clear`,
                                        checked === true,
                                    )
                                }
                            />
                            <Label htmlFor={`${field.field}__clear`}>
                                {t('admin.settings.secret_clear')}
                            </Label>
                        </div>
                    )}
                </div>
            );
        }

        return (
            <div className="space-y-2">
                <FormField
                    id={field.field}
                    label={label}
                    hint={hint}
                    error={error}
                >
                    {(props) => {
                        const common = {
                            ...props,
                            value: String(value),
                            required: field.required,
                        };

                        switch (field.type) {
                            case 'color':
                                return (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <input
                                            type="color"
                                            aria-label={label}
                                            value={
                                                /^#[0-9a-fA-F]{6}$/.test(
                                                    String(value),
                                                )
                                                    ? String(value)
                                                    : '#122170'
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    field.field,
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 w-12 cursor-pointer rounded-md border border-input bg-card p-1"
                                        />
                                        <Input
                                            {...common}
                                            translate="no"
                                            placeholder={t(
                                                'admin.settings.color_preset',
                                            )}
                                            className="w-36 font-mono"
                                            onChange={(event) =>
                                                form.setData(
                                                    field.field,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {String(value) !== '' && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    form.setData(
                                                        field.field,
                                                        '',
                                                    )
                                                }
                                            >
                                                {t(
                                                    'admin.settings.color_reset',
                                                )}
                                            </Button>
                                        )}
                                    </div>
                                );
                            case 'longtext':
                                return (
                                    <Textarea
                                        {...common}
                                        rows={3}
                                        lang={
                                            field.key.endsWith('.am')
                                                ? 'am'
                                                : 'en'
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                field.field,
                                                event.target.value,
                                            )
                                        }
                                    />
                                );
                            case 'choice':
                            case 'timezone': {
                                const options =
                                    field.type === 'timezone'
                                        ? Array.from(
                                              new Set([
                                                  ...timezones,
                                                  String(value),
                                              ]),
                                          )
                                        : (field.options ?? []);

                                return (
                                    <NativeSelect
                                        {...common}
                                        onChange={(event) =>
                                            form.setData(
                                                field.field,
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {options.map((option) => (
                                            <option key={option} value={option}>
                                                {field.type === 'timezone'
                                                    ? option
                                                    : (optional(
                                                          `admin.settings.options.${field.field}.${option}`,
                                                      ) ?? option)}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                );
                            }
                            default:
                                return (
                                    <Input
                                        {...common}
                                        type={
                                            field.type === 'int'
                                                ? 'number'
                                                : field.type === 'email'
                                                  ? 'email'
                                                  : field.type === 'url'
                                                    ? 'url'
                                                    : field.type === 'time'
                                                      ? 'time'
                                                      : 'text'
                                        }
                                        inputMode={
                                            field.type === 'int'
                                                ? 'numeric'
                                                : undefined
                                        }
                                        min={
                                            field.type === 'int'
                                                ? field.min
                                                : undefined
                                        }
                                        max={
                                            field.type === 'int'
                                                ? field.max
                                                : undefined
                                        }
                                        translate={
                                            ['email', 'url', 'text'].includes(
                                                field.type,
                                            ) &&
                                            field.key !== 'app.name' &&
                                            field.key !== 'mail.from.name'
                                                ? 'no'
                                                : undefined
                                        }
                                        className={cn(
                                            field.type === 'int' &&
                                                'w-36 tabular-nums',
                                        )}
                                        onChange={(event) =>
                                            form.setData(
                                                field.field,
                                                event.target.value,
                                            )
                                        }
                                    />
                                );
                        }
                    }}
                </FormField>
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    {changed}
                    {isPlaceholder(value) && (
                        <span className="flex items-center gap-1.5 font-medium text-destructive">
                            <TriangleAlert
                                aria-hidden="true"
                                className="size-4"
                            />
                            {t('admin.settings.placeholder_warning')}
                        </span>
                    )}
                </div>
                {field.key === 'permits.appearance.theme' && (
                    <ul className="flex flex-wrap gap-2" aria-hidden="true">
                        {(field.options ?? []).map((theme) => (
                            <li key={theme}>
                                <button
                                    type="button"
                                    tabIndex={-1}
                                    onClick={() =>
                                        form.setData(field.field, theme)
                                    }
                                    className={cn(
                                        'flex items-center gap-2 rounded-full border border-border px-3 py-1.5 text-sm',
                                        value === theme && 'ring-2 ring-ring',
                                    )}
                                >
                                    <span
                                        className="size-4 rounded-full"
                                        style={{
                                            background: THEME_SWATCH[theme],
                                        }}
                                    />
                                    {optional(
                                        `admin.settings.options.${field.field}.${theme}`,
                                    ) ?? theme}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        );
    };

    return (
        <AdminSettingsLayout current={section} sections={sections}>
            <div className="flex flex-col gap-6">
                <p className="text-muted-foreground">
                    {t(
                        `admin.settings.section_intros.${section}` as TranslationKey,
                    )}
                </p>

                <form
                    onSubmit={submit}
                    noValidate
                    className="flex flex-col gap-6"
                >
                    {groups.map((group) => (
                        <section
                            key={group}
                            aria-labelledby={`group-${group}`}
                            className="space-y-5 rounded-card border border-border bg-card p-5 sm:p-6"
                        >
                            <h2
                                id={`group-${group}`}
                                className="text-section-title"
                            >
                                {t(
                                    `admin.settings.groups.${group}` as TranslationKey,
                                )}
                            </h2>
                            {fields
                                .filter((field) => field.group === group)
                                .map((field) => (
                                    <div key={field.key}>{control(field)}</div>
                                ))}
                        </section>
                    ))}

                    <div>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <Save aria-hidden="true" />
                            )}
                            {t('admin.settings.save')}
                        </Button>
                    </div>
                </form>

                {TESTABLE.includes(section) && (
                    <form
                        onSubmit={sendTest}
                        noValidate
                        aria-labelledby="test-title"
                        className="space-y-4 rounded-card border border-dashed border-border p-5 sm:p-6"
                    >
                        <div className="space-y-1">
                            <h2 id="test-title" className="text-section-title">
                                {t('admin.settings.test.title')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    `admin.settings.test.${section}_intro` as TranslationKey,
                                )}
                            </p>
                        </div>
                        {section === 'sms' && (
                            <div className="grid max-w-xs gap-1.5">
                                <Label htmlFor="phone">
                                    {t('admin.settings.test.phone')}
                                </Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="off"
                                    translate="no"
                                    value={phone}
                                    onChange={(event) =>
                                        setPhone(event.target.value)
                                    }
                                />
                                <InputError message={form.errors.phone} />
                            </div>
                        )}
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={testing}
                        >
                            {testing ? (
                                <Spinner />
                            ) : (
                                <Send aria-hidden="true" />
                            )}
                            {t('admin.settings.test.send')}
                        </Button>
                    </form>
                )}
            </div>
        </AdminSettingsLayout>
    );
}
