import { Head, router, useForm } from '@inertiajs/react';
import { Download, RefreshCw, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { DualDate } from '@/components/dual-date';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, issues, store } from '@/routes/admin/imports';
import type {
    BreadcrumbItem,
    DualDate as DualDateValue,
    Paginated,
} from '@/types';
import type { TranslationKey } from '@/types/translations';

type ImportStatus = 'pending' | 'running' | 'completed' | 'failed';

type Batch = {
    id: number;
    file_name: string;
    status: ImportStatus;
    dry_run: boolean;
    totals: Record<string, number> | null;
    started_by: string;
    created_at: DualDateValue | null;
    created_time: string | null;
    error: string | null;
    has_issues_file: boolean;
};

const TOTALS = [
    'rows',
    'imported',
    'already_imported',
    'skipped',
    'warnings',
    'organizations_created',
    'vehicles_created',
    'payments_created',
    'not_blocking',
] as const;

const STATUS_TONE: Record<ImportStatus, string> = {
    pending: 'bg-muted text-muted-foreground',
    running: 'bg-highlight text-highlight-foreground',
    completed: 'bg-success text-success-foreground',
    failed: 'bg-destructive text-white',
};

/**
 * The legacy import runner (spec §11): upload the Google Form CSV, run it as
 * a dry run or for real, and download the issues report of each run.
 */
export default function ImportsIndex({
    batches,
}: {
    batches: Paginated<Batch>;
}) {
    const { t } = useTranslation();
    const form = useForm<{ file: File | null; dry_run: boolean }>({
        file: null,
        dry_run: true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('file'),
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('imports.title'), href: index() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('imports.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('imports.title')}
                    description={t('imports.intro')}
                />

                <form
                    onSubmit={submit}
                    noValidate
                    className="max-w-2xl space-y-5 rounded-2xl border border-border bg-card p-6"
                >
                    <FormField
                        id="file"
                        label={t('imports.fields.file')}
                        hint={t('imports.file_hint')}
                        error={form.errors.file}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                type="file"
                                accept=".csv,text/csv"
                                className="h-auto py-2"
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        )}
                    </FormField>

                    <label className="flex cursor-pointer items-start gap-3 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={form.data.dry_run}
                            onChange={(event) =>
                                form.setData('dry_run', event.target.checked)
                            }
                            className="mt-0.5 size-4 accent-primary"
                        />
                        {t('imports.fields.dry_run')}
                    </label>

                    <Button type="submit" size="lg" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <Upload aria-hidden="true" />
                        )}
                        {t('imports.submit')}
                    </Button>
                </form>

                <section aria-labelledby="batches-title" className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 id="batches-title" className="text-xl text-display">
                            {t('imports.batches')}
                        </h2>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.reload({ only: ['batches'] })}
                        >
                            <RefreshCw aria-hidden="true" />
                            {t('imports.refresh')}
                        </Button>
                    </div>

                    {batches.data.length === 0 ? (
                        <p className="text-muted-foreground">
                            {t('imports.empty')}
                        </p>
                    ) : (
                        <ul className="grid gap-3">
                            {batches.data.map((batch) => (
                                <li
                                    key={batch.id}
                                    className="space-y-3 rounded-2xl border border-border bg-card p-4 sm:p-5"
                                >
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                        <span className="font-extrabold tabular-nums">
                                            #{batch.id}
                                        </span>
                                        <span
                                            className={cn(
                                                'rounded-full px-2.5 py-0.5 text-xs font-bold',
                                                STATUS_TONE[batch.status],
                                            )}
                                        >
                                            {t(
                                                `imports.status.${batch.status}`,
                                            )}
                                        </span>
                                        {batch.dry_run && (
                                            <span className="rounded-full border border-border px-2.5 py-0.5 text-xs font-bold">
                                                {t('imports.dry_run')}
                                            </span>
                                        )}
                                        <span className="font-medium break-all">
                                            {batch.file_name}
                                        </span>
                                        <span className="flex flex-wrap items-baseline gap-x-2 text-sm text-muted-foreground">
                                            {batch.created_at && (
                                                <DualDate
                                                    date={batch.created_at}
                                                />
                                            )}
                                            <span className="tabular-nums">
                                                {batch.created_time}
                                            </span>
                                            <span>
                                                {t('imports.started_by', {
                                                    name: batch.started_by,
                                                })}
                                            </span>
                                        </span>
                                    </div>

                                    {batch.totals && (
                                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3 lg:grid-cols-5">
                                            {TOTALS.map((key) => (
                                                <div key={key}>
                                                    <dt className="text-eyebrow text-muted-foreground">
                                                        {t(
                                                            `imports.totals.${key}` as TranslationKey,
                                                        )}
                                                    </dt>
                                                    <dd className="text-lg font-bold tabular-nums">
                                                        {batch.totals?.[key] ??
                                                            0}
                                                    </dd>
                                                </div>
                                            ))}
                                        </dl>
                                    )}

                                    {batch.error && (
                                        <p
                                            role="alert"
                                            className="rounded-lg border-2 border-destructive px-3 py-2 text-sm"
                                        >
                                            {batch.error}
                                        </p>
                                    )}

                                    {batch.has_issues_file && (
                                        <Button asChild variant="outline">
                                            <a
                                                href={issues.url({
                                                    import: batch.id,
                                                })}
                                            >
                                                <Download aria-hidden="true" />
                                                {t('imports.download_issues')}
                                            </a>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <Pagination links={batches.links} meta={batches.meta} />
                </section>
            </div>
        </AppLayout>
    );
}
