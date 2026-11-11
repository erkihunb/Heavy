import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { PlateChip } from '@/components/plate-chip';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, index, show } from '@/routes/permits';
import type { BreadcrumbItem, Paginated, Permit } from '@/types';
import { PERMIT_STATUSES, PERMIT_TYPES } from '@/types/enums';
import type { PermitStatus, PermitType } from '@/types/enums';

type Props = {
    permits: Paginated<Permit>;
    filters: {
        status: PermitStatus | null;
        permit_type: PermitType | null;
        plate: string;
    };
    canCreate: boolean;
};

export default function PermitsIndex({ permits, filters, canCreate }: Props) {
    const { t } = useTranslation();
    const [status, setStatus] = useState<string>(filters.status ?? '');
    const [permitType, setPermitType] = useState<string>(
        filters.permit_type ?? '',
    );
    const [plate, setPlate] = useState(filters.plate);

    const filtered =
        filters.status !== null ||
        filters.permit_type !== null ||
        filters.plate !== '';

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            index.url(),
            {
                ...(status !== '' && { status }),
                ...(permitType !== '' && { permit_type: permitType }),
                ...(plate.trim() !== '' && { plate: plate.trim() }),
            },
            { preserveState: true, replace: true },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('permits.title'), href: index() },
    ];

    const newButton = canCreate ? (
        <Button asChild size="lg" className="w-full sm:w-auto">
            <Link href={create()}>
                <Plus aria-hidden="true" />
                {t('permits.new')}
            </Link>
        </Button>
    ) : undefined;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('permits.title')}
                    description={t('permits.intro')}
                    action={newButton}
                />

                {permits.meta.total === 0 && !filtered ? (
                    <div className="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-input px-6 py-16 text-center">
                        <span className="flex size-16 items-center justify-center rounded-xl bg-highlight text-highlight-foreground">
                            <FileText aria-hidden="true" className="size-8" />
                        </span>
                        <h2 className="text-2xl text-display">
                            {t('permits.empty.title')}
                        </h2>
                        <p className="max-w-sm text-muted-foreground">
                            {t('permits.empty.body')}
                        </p>
                    </div>
                ) : (
                    <>
                        <form
                            role="search"
                            onSubmit={search}
                            className="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto_auto] lg:items-end"
                        >
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="filter-status"
                                    className="text-eyebrow text-muted-foreground"
                                >
                                    {t('permits.filters.status')}
                                </label>
                                <NativeSelect
                                    id="filter-status"
                                    value={status}
                                    onChange={(event) =>
                                        setStatus(event.target.value)
                                    }
                                >
                                    <option value="">
                                        {t('permits.filters.all_statuses')}
                                    </option>
                                    {PERMIT_STATUSES.map((value) => (
                                        <option key={value} value={value}>
                                            {t(`permits.status.${value}`)}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="filter-type"
                                    className="text-eyebrow text-muted-foreground"
                                >
                                    {t('permits.filters.permit_type')}
                                </label>
                                <NativeSelect
                                    id="filter-type"
                                    value={permitType}
                                    onChange={(event) =>
                                        setPermitType(event.target.value)
                                    }
                                >
                                    <option value="">
                                        {t('permits.filters.all_types')}
                                    </option>
                                    {PERMIT_TYPES.map((value) => (
                                        <option key={value} value={value}>
                                            {t(`permits.types.${value}`)}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="filter-plate"
                                    className="text-eyebrow text-muted-foreground"
                                >
                                    {t('permits.filters.plate')}
                                </label>
                                <Input
                                    id="filter-plate"
                                    type="search"
                                    value={plate}
                                    maxLength={50}
                                    onChange={(event) =>
                                        setPlate(event.target.value)
                                    }
                                />
                            </div>
                            <Button type="submit" className="h-11">
                                <Search aria-hidden="true" />
                                {t('permits.filters.apply')}
                            </Button>
                            {filtered && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-11"
                                >
                                    <Link href={index()}>
                                        {t('permits.filters.clear')}
                                    </Link>
                                </Button>
                            )}
                        </form>

                        <p
                            className="text-sm text-muted-foreground tabular-nums"
                            aria-live="polite"
                        >
                            {permits.meta.total === 0
                                ? t('permits.no_results')
                                : t('permits.count', {
                                      count: permits.meta.total,
                                  })}
                        </p>

                        <ul className="grid gap-3">
                            {permits.data.map((permit) => (
                                <li
                                    key={permit.id}
                                    className="grid gap-4 rounded-xl border border-border bg-card p-4 sm:p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                                >
                                    <div className="space-y-3">
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                            <StatusBadge
                                                status={permit.effective_status}
                                            />
                                            <span className="font-bold">
                                                {t(
                                                    `permits.types.${permit.permit_type}`,
                                                )}
                                            </span>
                                            {permit.permit_number ? (
                                                <span
                                                    translate="no"
                                                    className="font-extrabold tracking-wide tabular-nums"
                                                >
                                                    {permit.permit_number}
                                                </span>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    {t('permits.not_numbered')}
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {(permit.vehicles ?? []).map(
                                                (vehicle) => (
                                                    <PlateChip
                                                        key={vehicle.id}
                                                        plate={vehicle.plate}
                                                    />
                                                ),
                                            )}
                                        </div>
                                        <p className="flex flex-wrap items-baseline gap-x-2 text-sm">
                                            <span className="text-eyebrow text-muted-foreground">
                                                {t('permits.fields.validity')}
                                            </span>
                                            <DualDate
                                                date={permit.valid_from}
                                            />
                                            <span aria-hidden="true">–</span>
                                            <DualDate date={permit.valid_to} />
                                        </p>
                                    </div>
                                    <Button asChild variant="outline">
                                        <Link
                                            href={show({ permit: permit.id })}
                                        >
                                            {t('permits.actions.view')}
                                        </Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>

                        <Pagination links={permits.links} meta={permits.meta} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
