import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
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
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { PlateChip } from '@/components/plate-chip';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show } from '@/routes/staff/permits';
import type { BreadcrumbItem, Paginated, Permit } from '@/types';
import { PERMIT_STATUSES, PERMIT_TYPES } from '@/types/enums';
import type { PermitStatus, PermitType } from '@/types/enums';

type Props = {
    permits: Paginated<Permit>;
    filters: {
        /** null = waiting for review (the default), "all" = every status. */
        status: PermitStatus | 'all' | null;
        permit_type: PermitType | null;
        plate: string;
        organization: string;
        submitted_from: string | null;
        submitted_to: string | null;
    };
};

/** Staff never see drafts or withdrawn applications. */
const STAFF_STATUSES = PERMIT_STATUSES.filter(
    (status) => status !== 'draft' && status !== 'withdrawn',
);

export default function StaffPermitsIndex({ permits, filters }: Props) {
    const { t } = useTranslation();
    const [status, setStatus] = useState<string>(filters.status ?? '');
    const [permitType, setPermitType] = useState<string>(
        filters.permit_type ?? '',
    );
    const [plate, setPlate] = useState(filters.plate);
    const [organization, setOrganization] = useState(filters.organization);
    const [submittedFrom, setSubmittedFrom] = useState(
        filters.submitted_from ?? '',
    );
    const [submittedTo, setSubmittedTo] = useState(filters.submitted_to ?? '');

    const filtered =
        filters.status !== null ||
        filters.permit_type !== null ||
        filters.plate !== '' ||
        filters.organization !== '' ||
        filters.submitted_from !== null ||
        filters.submitted_to !== null;

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            index.url(),
            {
                ...(status !== '' && { status }),
                ...(permitType !== '' && { permit_type: permitType }),
                ...(plate.trim() !== '' && { plate: plate.trim() }),
                ...(organization.trim() !== '' && {
                    organization: organization.trim(),
                }),
                ...(submittedFrom !== '' && { submitted_from: submittedFrom }),
                ...(submittedTo !== '' && { submitted_to: submittedTo }),
            },
            { preserveState: true, replace: true },
        );
    };

    const waiting = (hours: number) =>
        hours < 48
            ? t('permits.staff.waiting_hours', { count: hours })
            : t('permits.staff.waiting_days', {
                  count: Math.floor(hours / 24),
              });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('permits.staff.queue_title'), href: index() },
    ];

    const labelClass = 'text-eyebrow text-muted-foreground';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.staff.queue_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('permits.staff.queue_title')}
                    description={t('permits.staff.queue_intro')}
                />

                <form
                    role="search"
                    onSubmit={search}
                    className="grid gap-4 rounded-card border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div className="grid content-start gap-1.5">
                        <label htmlFor="filter-status" className={labelClass}>
                            {t('permits.filters.status')}
                        </label>
                        <NativeSelect
                            id="filter-status"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">
                                {t('permits.filters.waiting_review')}
                            </option>
                            <option value="all">
                                {t('permits.filters.all_statuses')}
                            </option>
                            {STAFF_STATUSES.map((value) => (
                                <option key={value} value={value}>
                                    {t(`permits.status.${value}`)}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid content-start gap-1.5">
                        <label htmlFor="filter-type" className={labelClass}>
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
                    <div className="grid content-start gap-1.5">
                        <label htmlFor="filter-plate" className={labelClass}>
                            {t('permits.filters.plate')}
                        </label>
                        <Input
                            id="filter-plate"
                            type="search"
                            value={plate}
                            maxLength={50}
                            onChange={(event) => setPlate(event.target.value)}
                        />
                    </div>
                    <div className="grid content-start gap-1.5">
                        <label
                            htmlFor="filter-organization"
                            className={labelClass}
                        >
                            {t('permits.filters.organization')}
                        </label>
                        <Input
                            id="filter-organization"
                            type="search"
                            value={organization}
                            maxLength={100}
                            onChange={(event) =>
                                setOrganization(event.target.value)
                            }
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <EthiopianDateInput
                            id="filter-submitted-from"
                            label={t('permits.filters.submitted_from')}
                            value={submittedFrom}
                            yearsBefore={3}
                            yearsAfter={0}
                            onChange={setSubmittedFrom}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <EthiopianDateInput
                            id="filter-submitted-to"
                            label={t('permits.filters.submitted_to')}
                            value={submittedTo}
                            yearsBefore={3}
                            yearsAfter={0}
                            onChange={setSubmittedTo}
                        />
                    </div>
                    <div className="flex flex-col gap-3 sm:col-span-2 sm:flex-row lg:col-span-4">
                        <Button type="submit" className="h-11">
                            <Search aria-hidden="true" />
                            {t('permits.filters.apply')}
                        </Button>
                        {filtered && (
                            <Button asChild variant="outline" className="h-11">
                                <Link href={index()}>
                                    {t('permits.filters.clear')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </form>

                <p
                    className="text-sm text-muted-foreground tabular-nums"
                    aria-live="polite"
                >
                    {permits.meta.total === 0
                        ? t('permits.staff.queue_empty')
                        : t('permits.staff.queue_count', {
                              count: permits.meta.total,
                          })}
                </p>

                <TableCard>
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('permits.fields.status')}</Th>
                                <Th>{t('permits.filters.organization')}</Th>
                                <Th>{t('permits.fields.permit_type')}</Th>
                                <Th>{t('permits.fields.permit_number')}</Th>
                                <Th>{t('permits.fields.vehicles')}</Th>
                                <Th>{t('permits.fields.validity')}</Th>
                                <Th>{t('permits.fields.submitted')}</Th>
                                <Th align="right">
                                    {t('permits.fields.waiting')}
                                </Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {permits.data.length === 0 ? (
                                <EmptyRow colSpan={9}>
                                    {t('permits.staff.queue_empty')}
                                </EmptyRow>
                            ) : (
                                permits.data.map((permit) => (
                                    <Tr key={permit.id}>
                                        <Td>
                                            <StatusBadge
                                                status={permit.effective_status}
                                            />
                                        </Td>
                                        <Td className="font-medium">
                                            {permit.organization}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {t(
                                                `permits.types.${permit.permit_type}`,
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {permit.permit_number ? (
                                                <span
                                                    translate="no"
                                                    className="font-semibold tabular-nums"
                                                >
                                                    {permit.permit_number}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </Td>
                                        <Td>
                                            <span className="flex flex-wrap gap-1.5">
                                                {(permit.vehicles ?? []).map(
                                                    (vehicle) => (
                                                        <PlateChip
                                                            key={vehicle.id}
                                                            plate={
                                                                vehicle.plate
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <span className="flex flex-wrap items-baseline gap-x-1.5">
                                                <DualDate
                                                    date={permit.valid_from}
                                                />
                                                <span aria-hidden="true">
                                                    –
                                                </span>
                                                <DualDate
                                                    date={permit.valid_to}
                                                />
                                            </span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {permit.submitted_at ? (
                                                <DualDate
                                                    date={permit.submitted_at}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </Td>
                                        <Td
                                            numeric
                                            className="font-semibold whitespace-nowrap"
                                        >
                                            {typeof permit.waiting_hours ===
                                            'number'
                                                ? waiting(permit.waiting_hours)
                                                : '—'}
                                        </Td>
                                        <Td align="right">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={show({
                                                        permit: permit.id,
                                                    })}
                                                >
                                                    {t('permits.actions.view')}
                                                </Link>
                                            </Button>
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>

                <Pagination links={permits.links} meta={permits.meta} />
            </div>
        </AppLayout>
    );
}
