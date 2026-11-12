import { useEffect, useState } from 'react';
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

const RADIUS: Record<string, string> = {
    none: '0',
    sm: '4px',
    md: '8px',
    lg: '12px',
    xl: '18px',
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
    const radius =
        RADIUS[text(values, 'permits__appearance__card_radius')] ?? '10px';
    const button = text(values, 'permits__appearance__button_style');
    const buttonRadius =
        button === 'square' ? '2px' : button === 'pill' ? '999px' : '8px';

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
                        style={{ borderRadius: radius }}
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
