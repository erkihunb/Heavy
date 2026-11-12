import { useTranslation } from '@/lib/i18n';

export type CodePreview = {
    year: number;
    last: { yearly: number | null; never: number | null };
    branch: string | null;
};

type Values = Record<string, unknown>;

const text = (values: Values, field: string): string =>
    typeof values[field] === 'string' ? (values[field] as string) : '';

const number = (values: Values, field: string, fallback: number): number => {
    const value = Number.parseInt(text(values, field), 10);

    return Number.isFinite(value) ? value : fallback;
};

/**
 * The next permit number under the rule being edited; mirrors
 * App\Domain\Permits\PermitNumberGenerator::format().
 */
export function previewPermitNumber(
    values: Values,
    preview: CodePreview,
): string {
    const reset = text(values, 'permits__number_reset') || 'yearly';
    const start = Math.max(1, number(values, 'permits__number_start', 1));
    const last = reset === 'never' ? preview.last.never : preview.last.yearly;
    const next = last === null ? start : last + 1;
    const digits = number(values, 'permits__number_digits', 6);
    const year =
        text(values, 'permits__number_year_format') === 'short'
            ? String(preview.year).slice(-2)
            : String(preview.year);

    const parts: Record<string, string> = {
        '{prefix}': text(values, 'permits__number_prefix'),
        '{branch}': preview.branch ?? '',
        '{type}': text(values, 'permits__number_type_codes__full_day'),
        '{year}': year,
        '{seq}': String(next).padStart(digits, '0'),
    };

    const formatted = text(values, 'permits__number_format').replace(
        /\{prefix\}|\{branch\}|\{type\}|\{year\}|\{seq\}/g,
        (token) => parts[token] ?? token,
    );

    // A part left empty takes its hyphen with it, as on the server.
    return formatted.replace(/-{2,}/g, '-').replace(/^-+|-+$/g, '');
}

const SAMPLE = '7K3PQX2MA9F4C8D6';

/** An example certificate code under the rule being edited. */
export function previewCertificateCode(values: Values): string | null {
    if (values.permits__certificate_code__enabled !== true) {
        return null;
    }

    const length = Math.min(
        16,
        Math.max(6, number(values, 'permits__certificate_code__length', 8)),
    );
    const groups =
        SAMPLE.slice(0, length)
            .match(/.{1,4}/g)
            ?.join('-') ?? SAMPLE.slice(0, 8);
    const prefix = text(values, 'permits__certificate_code__prefix').trim();

    return prefix === '' ? groups : `${prefix}-${groups}`;
}

/**
 * Live preview on System settings → Code rules: the next permit number and
 * an example certificate code, updated as the fields change, before saving.
 */
export function CodeRulesPreview({
    values,
    preview,
}: {
    values: Values;
    preview: CodePreview;
}) {
    const { t } = useTranslation();
    const certificate = previewCertificateCode(values);

    return (
        <section
            aria-labelledby="code-preview-title"
            aria-live="polite"
            className="rounded-card border-2 border-primary/40 bg-primary/5 p-5 sm:p-6"
        >
            <h2 id="code-preview-title" className="text-section-title">
                {t('admin.settings.preview.title')}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                {t('admin.settings.preview.intro')}
            </p>
            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                <div className="rounded-control border border-border bg-card px-4 py-3">
                    <dt className="text-sm font-medium text-muted-foreground">
                        {t('admin.settings.preview.permit_number')}
                    </dt>
                    <dd
                        className="mt-1 text-xl font-extrabold tracking-wide tabular-nums"
                        translate="no"
                    >
                        {previewPermitNumber(values, preview)}
                    </dd>
                    <dd className="mt-1 text-xs text-muted-foreground">
                        {t('admin.settings.preview.permit_number_hint', {
                            branch:
                                preview.branch ??
                                t('admin.settings.preview.head_office'),
                        })}
                    </dd>
                </div>
                <div className="rounded-control border border-border bg-card px-4 py-3">
                    <dt className="text-sm font-medium text-muted-foreground">
                        {t('admin.settings.preview.certificate_code')}
                    </dt>
                    <dd
                        className="mt-1 text-xl font-extrabold tracking-[0.08em] tabular-nums"
                        translate="no"
                    >
                        {certificate ?? (
                            <span className="text-base font-medium tracking-normal text-muted-foreground">
                                {t('admin.settings.preview.certificate_off')}
                            </span>
                        )}
                    </dd>
                </div>
            </dl>
        </section>
    );
}
