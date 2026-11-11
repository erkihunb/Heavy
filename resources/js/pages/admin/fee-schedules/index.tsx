import { Head, Link, router } from '@inertiajs/react';
import { Info, Pencil, Plus } from 'lucide-react';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
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

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
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

                <p className="flex gap-3 rounded-2xl border border-border bg-muted p-4 text-sm font-medium">
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

                {schedules.data.length === 0 ? (
                    <p className="text-muted-foreground">{t('fees.empty')}</p>
                ) : (
                    <ul className="grid gap-3">
                        {schedules.data.map((schedule) => (
                            <li
                                key={schedule.id}
                                className="grid gap-4 rounded-2xl border border-border bg-card p-4 sm:p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                            >
                                <div className="space-y-2">
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span className="font-bold">
                                            {t(
                                                `permits.types.${schedule.permit_type}`,
                                            )}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {schedule.vehicle_type?.name ??
                                                t('fees.all_vehicle_types')}
                                        </span>
                                        <span className="tabular-nums">
                                            {t('fees.days_range', {
                                                min: schedule.min_days,
                                                max: schedule.max_days,
                                            })}
                                        </span>
                                        <span className="text-lg font-extrabold tabular-nums">
                                            {schedule.amount.formatted}
                                        </span>
                                        <span
                                            className={
                                                schedule.is_current
                                                    ? 'rounded-full bg-success px-2.5 py-0.5 text-xs font-bold text-success-foreground'
                                                    : 'rounded-full bg-muted px-2.5 py-0.5 text-xs font-bold text-muted-foreground'
                                            }
                                        >
                                            {schedule.is_current
                                                ? t('fees.current')
                                                : t('fees.not_current')}
                                        </span>
                                    </div>
                                    <p className="flex flex-wrap items-baseline gap-x-2 text-sm">
                                        <span className={labelClass}>
                                            {t('fees.fields.effective')}
                                        </span>
                                        <DualDate
                                            date={schedule.effective_from}
                                        />
                                        <span aria-hidden="true">–</span>
                                        {schedule.effective_to ? (
                                            <DualDate
                                                date={schedule.effective_to}
                                            />
                                        ) : (
                                            <span>{t('fees.open_ended')}</span>
                                        )}
                                    </p>
                                </div>
                                <Button asChild variant="outline">
                                    <Link
                                        href={edit({
                                            fee_schedule: schedule.id,
                                        })}
                                    >
                                        <Pencil aria-hidden="true" />
                                        {t('permits.actions.edit')}
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination links={schedules.links} meta={schedules.meta} />
            </div>
        </AppLayout>
    );
}
