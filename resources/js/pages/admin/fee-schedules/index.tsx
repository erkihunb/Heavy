import { Head, Link, router } from '@inertiajs/react';
import { Info, Pencil, Plus } from 'lucide-react';
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
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { create, edit, index } from '@/routes/admin/fee-schedules';
import type { BreadcrumbItem, FeeSchedule, Paginated } from '@/types';
import { PERMIT_TYPES } from '@/types/enums';
import type { PermitType } from '@/types/enums';

type Props = {
    schedules: Paginated<FeeSchedule>;
    filters: { permit_type: PermitType | null };
};

export default function FeeSchedulesIndex({ schedules, filters }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('fees.title'), href: index() },
    ];

    const filter = (permitType: string) =>
        router.get(
            index.url(),
            permitType === '' ? {} : { permit_type: permitType },
            { preserveState: true, replace: true },
        );

    const labelClass = 'text-eyebrow text-muted-foreground';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('fees.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('fees.title')}
                    description={t('fees.intro')}
                    action={
                        <Button asChild size="lg" className="w-full sm:w-auto">
                            <Link href={create()}>
                                <Plus aria-hidden="true" />
                                {t('fees.new')}
                            </Link>
                        </Button>
                    }
                />

                <p className="flex gap-3 rounded-card border border-border bg-muted p-4 text-sm font-medium">
                    <Info aria-hidden="true" className="size-5 shrink-0" />
                    {t('fees.placeholder_notice')}
                </p>

                <div className="grid max-w-xs gap-1.5">
                    <label htmlFor="filter-type" className={labelClass}>
                        {t('fees.fields.permit_type')}
                    </label>
                    <NativeSelect
                        id="filter-type"
                        value={filters.permit_type ?? ''}
                        onChange={(event) => filter(event.target.value)}
                    >
                        <option value="">
                            {t('permits.filters.all_types')}
                        </option>
                        {PERMIT_TYPES.map((type) => (
                            <option key={type} value={type}>
                                {t(`permits.types.${type}`)}
                            </option>
                        ))}
                    </NativeSelect>
                </div>

                <TableCard>
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('fees.fields.permit_type')}</Th>
                                <Th>{t('fees.fields.vehicle_type')}</Th>
                                <Th>{t('fees.fields.days')}</Th>
                                <Th align="right">{t('fees.fields.amount')}</Th>
                                <Th>{t('fees.fields.effective')}</Th>
                                <Th>{t('common.status')}</Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {schedules.data.length === 0 ? (
                                <EmptyRow colSpan={7}>
                                    {t('fees.empty')}
                                </EmptyRow>
                            ) : (
                                schedules.data.map((schedule) => (
                                    <Tr key={schedule.id}>
                                        <Td className="font-medium">
                                            {t(
                                                `permits.types.${schedule.permit_type}`,
                                            )}
                                        </Td>
                                        <Td muted={!schedule.vehicle_type}>
                                            {schedule.vehicle_type?.name ??
                                                t('fees.all_vehicle_types')}
                                        </Td>
                                        <Td className="whitespace-nowrap tabular-nums">
                                            {t('fees.days_range', {
                                                min: schedule.min_days,
                                                max: schedule.max_days,
                                            })}
                                        </Td>
                                        <Td
                                            numeric
                                            className="font-semibold whitespace-nowrap"
                                        >
                                            {schedule.amount.formatted}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <span className="flex flex-wrap items-baseline gap-x-1.5">
                                                <DualDate
                                                    date={
                                                        schedule.effective_from
                                                    }
                                                />
                                                <span aria-hidden="true">
                                                    –
                                                </span>
                                                {schedule.effective_to ? (
                                                    <DualDate
                                                        date={
                                                            schedule.effective_to
                                                        }
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        {t('fees.open_ended')}
                                                    </span>
                                                )}
                                            </span>
                                        </Td>
                                        <Td>
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                                    schedule.is_current
                                                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900'
                                                        : 'bg-muted text-muted-foreground ring-border',
                                                )}
                                            >
                                                {schedule.is_current
                                                    ? t('fees.current')
                                                    : t('fees.not_current')}
                                            </span>
                                        </Td>
                                        <Td align="right">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={edit({
                                                        fee_schedule:
                                                            schedule.id,
                                                    })}
                                                >
                                                    <Pencil aria-hidden="true" />
                                                    {t('permits.actions.edit')}
                                                </Link>
                                            </Button>
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>

                <Pagination links={schedules.links} meta={schedules.meta} />
            </div>
        </AppLayout>
    );
}
