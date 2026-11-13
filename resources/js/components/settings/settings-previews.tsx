import { useEffect, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { useTranslation } from '@/lib/i18n';

type Values = Record<string, unknown>;

const text = (values: Values, field: string): string =>
    typeof values[field] === 'string' ? (values[field] as string) : '';

/** A picture chosen in the form, or the saved one. */
function useImage(file: unknown, saved: string | null): string | null {
    const [url, setUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!(file instanceof File)) {
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        // A blob URL is an external resource: hand it to React after it exists.
        queueMicrotask(() => setUrl(objectUrl));

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return file instanceof File ? url : saved;
}

/**
 * Preview on System settings → General: the system name, logo and sign-in
 * message as people will see them, before saving.
 */
export function BrandingPreview({
    values,
    saved,
}: {
    values: Values;
    saved: { logo: string | null; favicon: string | null };
}) {
    const { t, locale } = useTranslation();
    const logo = useImage(values.permits__branding__logo, saved.logo);
    const favicon = useImage(values.permits__branding__favicon, saved.favicon);
    const removed = (values.remove_assets as string[] | undefined) ?? [];
    const message = text(
        values,
        `permits__branding__login_message__${locale === 'am' ? 'am' : 'en'}`,
    );

    return (
        <section
            aria-labelledby="branding-preview-title"
            className="rounded-card border-2 border-primary/40 bg-primary/5 p-5 sm:p-6"
        >
            <h2 id="branding-preview-title" className="text-section-title">
                {t('admin.settings.preview.branding')}
            </h2>
            <div className="mt-4 overflow-hidden rounded-card border border-border bg-card">
                <div className="flex items-center gap-2 border-b border-border bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                    {favicon &&
                    !removed.includes('permits__branding__favicon') ? (
                        <img src={favicon} alt="" className="size-4" />
                    ) : (
                        <span className="size-4 rounded-sm bg-muted-foreground/30" />
                    )}
                    <span className="truncate">
                        {text(values, 'app__name')}
                    </span>
                </div>
                <div className="flex flex-col items-center gap-3 px-6 py-6 text-center">
                    {logo && !removed.includes('permits__branding__logo') ? (
                        <img
                            src={logo}
                            alt=""
                            className="size-16 object-contain"
                        />
                    ) : (
                        <span className="size-16 rounded-full border-2 border-dashed border-border" />
                    )}
                    <p className="text-lg font-bold">
                        {text(values, 'app__name')}
                    </p>
                    {message && (
                        <p className="max-w-sm rounded-control bg-muted px-3 py-2 text-sm whitespace-pre-line">
                            {message}
                        </p>
                    )}
                </div>
            </div>
        </section>
    );
}

const THEME_PRIMARY: Record<string, string> = {
    navy: '#122170',
    forest: '#166534',
    sunset: '#b4410f',
    graphite: '#27272a',
};

/**
 * Live preview on System settings → Appearance: a small screen drawn with the
 * colours and shapes being chosen, before saving.
 */
export function ThemePreview({ values }: { values: Values }) {
    const { t } = useTranslation();
    const theme = text(values, 'permits__appearance__theme') || 'navy';
    const primary =
        text(values, 'permits__appearance__primary_color') ||
        THEME_PRIMARY[theme] ||
        THEME_PRIMARY.navy;
    const accent = text(values, 'permits__appearance__accent_color') || primary;
    const sidebar =
        text(values, 'permits__appearance__sidebar_color') || '#ffffff';
    // The choices are applied to the page while they are edited (useLiveAppearance),
    // so the page's own corner and shadow tokens already show the chosen style.
    const radius = 'var(--radius-panel)';
    const cardRadius = 'var(--radius-card)';
    const button = text(values, 'permits__appearance__button_style');
    const buttonRadius = button === 'pill' ? '999px' : 'var(--radius-control)';

    return (
        <section
            aria-labelledby="theme-preview-title"
            className="rounded-card border-2 border-primary/40 bg-primary/5 p-5 sm:p-6"
        >
            <h2 id="theme-preview-title" className="text-section-title">
                {t('admin.settings.preview.theme')}
            </h2>
            <div
                className="mt-4 flex h-44 overflow-hidden border border-border bg-background"
                style={{ borderRadius: radius }}
                aria-hidden="true"
            >
                <div
                    className="flex w-24 flex-col gap-2 border-r border-border p-3"
                    style={{ background: sidebar }}
                >
                    <span
                        className="h-2.5 w-14 rounded-full"
                        style={{ background: primary }}
                    />
                    <span className="h-2 w-16 rounded-full bg-black/10" />
                    <span className="h-2 w-12 rounded-full bg-black/10" />
                    <span className="h-2 w-14 rounded-full bg-black/10" />
                </div>
                <div className="flex flex-1 flex-col gap-3 p-4">
                    <div
                        className="border border-border bg-card p-3"
                        style={{
                            borderRadius: cardRadius,
                            boxShadow: 'var(--elev-md)',
                        }}
                    >
                        <p className="text-sm font-bold">
                            {t('admin.settings.preview.sample_card')}
                        </p>
                        <span
                            className="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-bold text-white"
                            style={{ background: accent }}
                        >
                            {t('admin.settings.preview.sample_badge')}
                        </span>
                    </div>
                    <div className="mt-auto flex gap-2">
                        <span
                            className="px-3 py-1.5 text-xs font-bold text-white"
                            style={{
                                background: primary,
                                borderRadius: buttonRadius,
                            }}
                        >
                            {t('admin.settings.preview.sample_button')}
                        </span>
                        <span
                            className="border border-border px-3 py-1.5 text-xs font-bold"
                            style={{ borderRadius: buttonRadius }}
                        >
                            {t('admin.settings.preview.sample_secondary')}
                        </span>
                    </div>
                </div>
            </div>
        </section>
    );
}

export type MailInUse = {
    field: string;
    value: string | null;
    set: boolean;
    source: 'settings' | 'server';
};

/**
 * E-mail: the mail settings in effect now and where each comes from, so an
 * Admin can tell a value saved here from one in the server configuration.
 */
export function MailInUsePanel({ rows }: { rows: MailInUse[] }) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby="in-use-title"
            className="rounded-card border border-border bg-card p-5 sm:p-6"
        >
            <h2 id="in-use-title" className="text-section-title">
                {t('admin.settings.in_use.title')}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                {t('admin.settings.in_use.intro')}
            </p>
            <dl className="mt-4 divide-y divide-border">
                {rows.map((row) => (
                    <div
                        key={row.field}
                        className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2.5"
                    >
                        <dt className="text-sm text-muted-foreground">
                            {t(
                                `admin.settings.fields.${row.field}` as Parameters<
                                    typeof t
                                >[0],
                            )}
                        </dt>
                        <dd className="flex items-center gap-2 text-sm">
                            <span className="font-semibold" translate="no">
                                {row.value ??
                                    (row.set
                                        ? t('admin.settings.in_use.set')
                                        : t('admin.settings.in_use.not_set'))}
                            </span>
                            <span
                                className={
                                    row.source === 'settings'
                                        ? 'rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary'
                                        : 'rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
                                }
                            >
                                {row.source === 'settings'
                                    ? t('admin.settings.in_use.from_settings')
                                    : t('admin.settings.in_use.from_server')}
                            </span>
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

/*
 * How each interface style looks, drawn with its own literal values so all
 * four can be compared side by side (the page itself shows only one style).
 * Mirrors the tokens per [data-ui-style] in resources/css/app.css.
 */
const STYLE_SWATCH: Record<
    string,
    {
        page: string;
        card: string;
        edge: string;
        radius: string;
        control: string;
        shadow: string;
        text: string;
        line: string;
    }
> = {
    swiss: {
        page: '#f6f6f6',
        card: '#ffffff',
        edge: '1px solid #dcdcdc',
        radius: '2px',
        control: '2px',
        shadow: 'none',
        text: '#141414',
        line: '#d4d4d4',
    },
    classic: {
        page: '#f6f6f6',
        card: '#edf1fb',
        edge: '1px solid #d5ddf1',
        radius: '8px',
        control: '6px',
        shadow: '0 1px 3px rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.08)',
        text: '#141414',
        line: '#c9d2ea',
    },
    soft: {
        page: '#eceef3',
        card: '#f2f3f7',
        edge: '1px solid transparent',
        radius: '16px',
        control: '10px',
        shadow: '5px 5px 12px rgb(163 170 182 / 0.55), -5px -5px 12px rgb(255 255 255 / 0.9)',
        text: '#141414',
        line: '#d5d8e0',
    },
    contrast: {
        page: '#ffffff',
        card: '#ffffff',
        edge: '2px solid #000000',
        radius: '2px',
        control: '2px',
        shadow: 'none',
        text: '#000000',
        line: '#000000',
    },
};

/**
 * System settings → Appearance → Interface style, as a row of pictures
 * instead of a list of names: a radio group (arrow keys move the choice).
 */
export function UiStylePicker({
    id,
    groupLabel,
    value,
    options,
    label,
    describedBy,
    onChange,
}: {
    id: string;
    groupLabel: string;
    value: string;
    options: string[];
    label: (option: string) => string;
    describedBy?: string;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();

    const move = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
        const step =
            event.key === 'ArrowRight' || event.key === 'ArrowDown'
                ? 1
                : event.key === 'ArrowLeft' || event.key === 'ArrowUp'
                  ? -1
                  : 0;

        if (step === 0) {
            return;
        }

        event.preventDefault();
        const next = options[(index + step + options.length) % options.length];
        onChange(next);
        document.getElementById(`${id}-${next}`)?.focus();
    };

    return (
        <div className="flex flex-col gap-2">
            <div
                id={id}
                role="radiogroup"
                aria-label={groupLabel}
                aria-describedby={describedBy}
                className="grid grid-cols-2 gap-3 lg:grid-cols-4"
            >
                {options.map((option, index) => {
                    const look = STYLE_SWATCH[option] ?? STYLE_SWATCH.swiss;
                    const checked = value === option;

                    return (
                        <button
                            key={option}
                            id={`${id}-${option}`}
                            type="button"
                            role="radio"
                            aria-checked={checked}
                            aria-label={label(option)}
                            aria-describedby={`${id}-${option}-about`}
                            tabIndex={checked ? 0 : -1}
                            onClick={() => onChange(option)}
                            onKeyDown={(event) => move(event, index)}
                            className={[
                                'group flex min-h-11 cursor-pointer flex-col gap-2 rounded-card border-2 bg-card p-2 text-start transition-colors duration-150',
                                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                checked
                                    ? 'border-primary'
                                    : 'border-border hover:border-border-strong',
                            ].join(' ')}
                        >
                            <span
                                aria-hidden="true"
                                className="flex h-20 items-center justify-center p-3"
                                style={{
                                    background: look.page,
                                    borderRadius: look.radius,
                                }}
                            >
                                <span
                                    className="flex w-full flex-col gap-1.5 p-2"
                                    style={{
                                        background: look.card,
                                        border: look.edge,
                                        borderRadius: look.radius,
                                        boxShadow: look.shadow,
                                    }}
                                >
                                    <span
                                        className="h-1.5 w-3/4"
                                        style={{
                                            background: look.text,
                                            borderRadius: '1px',
                                        }}
                                    />
                                    <span
                                        className="h-1 w-1/2"
                                        style={{
                                            background: look.line,
                                            borderRadius: '1px',
                                        }}
                                    />
                                    <span
                                        className="mt-0.5 h-2.5 w-10"
                                        style={{
                                            background: 'var(--primary)',
                                            borderRadius: look.control,
                                        }}
                                    />
                                </span>
                            </span>
                            <span className="flex items-start justify-between gap-2 px-1">
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold">
                                        {label(option)}
                                    </span>
                                    <span
                                        id={`${id}-${option}-about`}
                                        className="mt-0.5 block text-xs text-muted-foreground"
                                    >
                                        {t(
                                            `admin.settings.ui_styles.${option}` as Parameters<
                                                typeof t
                                            >[0],
                                        )}
                                    </span>
                                </span>
                                <span
                                    aria-hidden="true"
                                    className={[
                                        'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border-2',
                                        checked
                                            ? 'border-primary'
                                            : 'border-border-strong',
                                    ].join(' ')}
                                >
                                    {checked && (
                                        <span className="size-2 rounded-full bg-primary" />
                                    )}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>
            <p className="text-xs text-muted-foreground">
                {t('admin.settings.ui_styles.live')}
            </p>
        </div>
    );
}
