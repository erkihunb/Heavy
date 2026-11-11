import { Head, router, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, RefreshCw } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    EmptyRow,
    Table,
    TableCard,
    TBody,
    Td,
    Th,
    THead,
    Tr,
} from '@/components/data-table';
import { DualDate } from '@/components/dual-date';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { download, index, store } from '@/routes/staff/exports';
import { index as reports } from '@/routes/staff/reports';
import type {
    BreadcrumbItem,
    DualDate as DualDateValue,
    Paginated,
} from '@/types';
import { PAYMENT_STATUSES, PERMIT_STATUSES } from '@/types/enums';
import type { ExportDataset, ExportFormat, ExportStatus } from '@/types/enums';

type ExportRow = {
    id: number;
    dataset: ExportDataset;
    format: ExportFormat;
    filters: {
        status: string | null;
        from: string | null;
        to: string | null;
    } | null;
    status: ExportStatus;
    rows: number | null;
    created_at: DualDateValue | null;
    created_time: string | null;
    can_download: boolean;
};

type Props = {
    exports: Paginated<ExportRow>;
    datasets: ExportDataset[];
    formats: ExportFormat[];
    keepDays: number;
};

const STATUS_TONE: Record<ExportStatus, string> = {
    pending: 'bg-muted text-muted-foreground ring-border',
    running:
        'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
    completed:
        'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900',
    failed: 'bg-red-50 text-red-800 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900',
};

/** Statuses staff can export permits by: never drafts or withdrawn applications. */
const PERMIT_EXPORT_STATUSES = PERMIT_STATUSES.filter(
    (status) => status !== 'draft' && status !== 'withdrawn',
);

/**
 * Spreadsheet exports (spec §11): ask for one, it is built in the background,
 * and it appears here (and by e-mail) when ready.
 */
export default function Exports({
    exports,
    datasets,
    formats,
    keepDays,
}: Props) {
    const { t } = useTranslation();
    const form = useForm<{
        dataset: ExportDataset;
        format: ExportFormat;
        status: string;
        from: string;
        to: string;
    }>({
        dataset: datasets[0],
        format: formats[0],
        status: '',
        from: '',
        to: '',
    });

    const statuses =
        form.data.dataset === 'payments'
            ? PAYMENT_STATUSES.map((status) => ({
                  value: status,
                  label: t(`payments.status.${status}`),
              }))
            : PERMIT_EXPORT_STATUSES.map((status) => ({
                  value: status,
                  label: t(`permits.status.${status}`),
              }));

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('reports.title'), href: reports() },
        { title: t('reports.exports.title'), href: index() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('reports.exports.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('reports.exports.title')}
                    description={t('reports.exports.intro', { days: keepDays })}
                />

                <form
                    onSubmit={submit}
                    noValidate
                    aria-labelledby="new-export-title"
                    className="max-w-3xl space-y-5 rounded-card border border-border bg-card p-5"
                >
                    <h2 id="new-export-title" className="text-section-title">
                        {t('reports.exports.new')}
                    </h2>

                    <div className="grid gap-5 sm:grid-cols-3">
                        <FormField
                            id="dataset"
                            label={t('reports.exports.fields.dataset')}
                            error={form.errors.dataset}
                        >
                            {(control) => (
                                <NativeSelect
                                    {...control}
                                    value={form.data.dataset}
                                    onChange={(event) =>
                                        form.setData({
                                            ...form.data,
                                            dataset: event.target
                                                .value as ExportDataset,
                                            status: '',
                                        })
                                    }
                                >
                                    {datasets.map((dataset) => (
                                        <option key={dataset} value={dataset}>
                                            {t(
                                                `reports.exports.datasets.${dataset}`,
                                            )}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>

                        <FormField
                            id="format"
                            label={t('reports.exports.fields.format')}
                            error={form.errors.format}
                        >
                            {(control) => (
                                <NativeSelect
                                    {...control}
                                    value={form.data.format}
                                    onChange={(event) =>
                                        form.setData(
                                            'format',
                                            event.target.value as ExportFormat,
                                        )
                                    }
                                >
                                    {formats.map((format) => (
                                        <option key={format} value={format}>
                                            {t(
                                                `reports.exports.formats.${format}`,
                                            )}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>

                        <FormField
                            id="status"
                            label={t('reports.exports.fields.status')}
                            error={form.errors.status}
                        >
                            {(control) => (
                                <NativeSelect
                                    {...control}
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('reports.exports.any_status')}
                                    </option>
                                    {statuses.map((status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                    </div>

                    <div className="space-y-2">
                        <div className="grid gap-5 md:grid-cols-2">
                            <EthiopianDateInput
                                id="from"
                                label={t('reports.exports.fields.from')}
                                value={form.data.from}
                                error={form.errors.from}
                                yearsBefore={3}
                                yearsAfter={1}
                                onChange={(iso) => form.setData('from', iso)}
                            />
                            <EthiopianDateInput
                                id="to"
                                label={t('reports.exports.fields.to')}
                                value={form.data.to}
                                error={form.errors.to}
                                yearsBefore={3}
                                yearsAfter={1}
                                onChange={(iso) => form.setData('to', iso)}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('reports.exports.date_hint')}
                        </p>
                    </div>

                    <InputError message={form.errors.dataset} />

                    <Button type="submit" size="lg" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <FileSpreadsheet aria-hidden="true" />
                        )}
                        {t('reports.exports.submit')}
                    </Button>
                </form>

                <section aria-labelledby="mine-title" className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 id="mine-title" className="text-section-title">
                            {t('reports.exports.mine')}
                        </h2>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.reload({ only: ['exports'] })}
                        >
                            <RefreshCw aria-hidden="true" />
                            {t('reports.exports.refresh')}
                        </Button>
                    </div>

                    <TableCard>
                        <Table>
                            <THead>
                                <tr>
                                    <Th align="right">#</Th>
                                    <Th>{t('common.status')}</Th>
                                    <Th>
                                        {t('reports.exports.fields.dataset')}
                                    </Th>
                                    <Th>
                                        {t('reports.exports.fields.format')}
                                    </Th>
                                    <Th align="right">
                                        {t('reports.exports.rows_heading')}
                                    </Th>
                                    <Th>{t('common.date')}</Th>
                                    <Th align="right">
                                        <span className="sr-only">
                                            {t('common.actions')}
                                        </span>
                                    </Th>
                                </tr>
                            </THead>
                            <TBody>
                                {exports.data.length === 0 ? (
                                    <EmptyRow colSpan={7}>
                                        {t('reports.exports.empty')}
                                    </EmptyRow>
                                ) : (
                                    exports.data.map((row) => (
                                        <Tr key={row.id}>
                                            <Td
                                                numeric
                                                className="font-semibold"
                                            >
                                                {row.id}
                                            </Td>
                                            <Td>
                                                <span
                                                    className={cn(
                                                        'inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                                        STATUS_TONE[row.status],
                                                    )}
                                                >
                                                    {t(
                                                        `reports.exports.status.${row.status}`,
                                                    )}
                                                </span>
                                            </Td>
                                            <Td className="font-medium">
                                                {t(
                                                    `reports.exports.datasets.${row.dataset}`,
                                                )}
                                            </Td>
                                            <Td className="whitespace-nowrap">
                                                {t(
                                                    `reports.exports.formats.${row.format}`,
                                                )}
                                            </Td>
                                            <Td numeric>{row.rows ?? '—'}</Td>
                                            <Td className="whitespace-nowrap">
                                                {row.created_at && (
                                                    <span className="flex items-baseline gap-2">
                                                        <DualDate
                                                            date={
                                                                row.created_at
                                                            }
                                                        />
                                                        <span className="text-xs text-muted-foreground tabular-nums">
                                                            {row.created_time}
                                                        </span>
                                                    </span>
                                                )}
                                            </Td>
                                            <Td align="right">
                                                {row.can_download && (
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <a
                                                            href={download.url({
                                                                export: row.id,
                                                            })}
                                                        >
                                                            <Download aria-hidden="true" />
                                                            {t(
                                                                'reports.exports.download',
                                                            )}
                                                        </a>
                                                    </Button>
                                                )}
                                            </Td>
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>

                    <Pagination links={exports.links} meta={exports.meta} />
                </section>
            </div>
        </AppLayout>
    );
}
