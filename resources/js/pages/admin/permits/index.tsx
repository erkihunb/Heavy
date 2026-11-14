import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Plus, Search, X } from 'lucide-react';
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
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { visitWithFilters } from '@/lib/filters';
import { useTranslation } from '@/lib/i18n';
import { index } from '@/routes/admin/permits';
import type {
    BreadcrumbItem,
    DualDate as DualDateValue,
    Paginated,
} from '@/types';
import { PERMIT_TYPES } from '@/types/enums';
import type { PermitStatus, PermitType } from '@/types/enums';

/** Mirrors App\Http\Controllers\Admin\PermitRegisterController::present(). */
type RegisterRow = {
    id: string;
    permit_number: string | null;
    permit_type: PermitType;
    status: PermitStatus;
    organization: string | null;
    applicant: string | null;
    plates: string[];
    valid_from: DualDateValue;
    valid_to: DualDateValue;
    created_at: DualDateValue | null;
    /** The page this viewer works on it from; null when none is open to them. */
    href: string | null;
};

type Filters = {
    search: string;
    status: string | null;
    permit_type: string | null;
    from: string | null;
    to: string | null;
};

type Props = {
    permits: Paginated<RegisterRow>;
    filters: Filters;
    statuses: PermitStatus[];
    /** The walk-in form, for those who take applications at the office; null otherwise. */
    createUrl: string | null;
};

/**
 * Permits (back office): every application and permit in the viewer's
 * branches, with a search over number, plate, organization, TIN and
 * applicant, filters on status, type, where it was registered and when it
 * was started, and a table that opens each permit
 * on the page the viewer works on it from. "Add new permit" opens the
 * walk-in form for those who take applications at the office.
 */
export default function PermitRegister({
    permits,
    filters,
    statuses,
    createUrl,
}: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);

    const apply = (next: Partial<Filters>) => {
        visitWithFilters(index.url(), { ...filters, ...next });
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        apply({ search: search.trim() });
    };

    const filtered =
        filters.search !== '' ||
        filters.status !== null ||
        filters.permit_type !== null ||
        filters.from !== null ||
        filters.to !== null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('register.title'), href: index() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('register.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('register.title')}
                    description={t('register.intro')}
                    action={
                        createUrl ? (
                            <Button asChild>
                                <Link href={createUrl}>
                                    <Plus aria-hidden="true" />
                                    {t('register.add_new')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <section
                    aria-label={t('register.search')}
                    className="space-y-4 rounded-card border border-border bg-card p-4 sm:p-5"
                >
                    <form
                        role="search"
                        onSubmit={submitSearch}
                        className="flex flex-col gap-2 sm:flex-row sm:items-end"
                    >
                        <div className="grid flex-1 gap-1.5">
                            <Label htmlFor="search">
                                {t('register.search')}
                            </Label>
                            <Input
                                id="search"
                                type="search"
                                value={search}
                                placeholder={t('register.search_placeholder')}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                        <Button type="submit">
                            <Search aria-hidden="true" />
                            {t('register.apply')}
                        </Button>
                    </form>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <div className="grid gap-1.5">
                            <Label htmlFor="status">
                                {t('register.status')}
                            </Label>
                            <NativeSelect
                                id="status"
                                value={filters.status ?? ''}
                                onChange={(event) =>
                                    apply({ status: event.target.value })
                                }
                            >
                                <option value="">
                                    {t('register.any_status')}
                                </option>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {t(`permits.status.${status}`)}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="permit_type">
                                {t('register.type')}
                            </Label>
                            <NativeSelect
                                id="permit_type"
                                value={filters.permit_type ?? ''}
                                onChange={(event) =>
                                    apply({ permit_type: event.target.value })
                                }
                            >
                                <option value="">
                                    {t('register.any_type')}
                                </option>
                                {PERMIT_TYPES.map((type) => (
                                    <option key={type} value={type}>
                                        {t(`permits.types.${type}`)}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                        <EthiopianDateInput
                            id="from"
                            label={t('register.from')}
                            value={filters.from ?? ''}
                            onChange={(iso) => apply({ from: iso })}
                        />
                        <EthiopianDateInput
                            id="to"
                            label={t('register.to')}
                            value={filters.to ?? ''}
                            onChange={(iso) => apply({ to: iso })}
                        />
                    </div>

                    {filtered && (
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    router.get(
                                        index.url(),
                                        {},
                                        { replace: true },
                                    );
                                }}
                            >
                                <X aria-hidden="true" />
                                {t('register.reset')}
                            </Button>
                        </div>
                    )}
                </section>

                <TableCard
                    title={t('register.count', {
                        count: String(permits.meta.total),
                    })}
                >
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('register.columns.permit')}</Th>
                                <Th>{t('register.columns.organization')}</Th>
                                <Th>{t('register.columns.vehicles')}</Th>
                                <Th>{t('register.columns.validity')}</Th>
                                <Th>{t('register.columns.status')}</Th>
                                <Th>{t('register.columns.started')}</Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {permits.data.length === 0 ? (
                                <EmptyRow colSpan={7}>
                                    {t('register.empty')}
                                </EmptyRow>
                            ) : (
                                permits.data.map((permit) => (
                                    <Tr key={permit.id}>
                                        <Td>
                                            <span
                                                translate="no"
                                                className="block font-semibold whitespace-nowrap tabular-nums"
                                            >
                                                {permit.permit_number ?? (
                                                    <span className="font-normal text-muted-foreground">
                                                        {t(
                                                            'register.no_number',
                                                        )}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {t(
                                                    `permits.types.${permit.permit_type}`,
                                                )}
                                            </span>
                                        </Td>
                                        <Td>
                                            <span className="block font-medium">
                                                {permit.organization ?? '—'}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {permit.applicant}
                                            </span>
                                        </Td>
                                        <Td>
                                            <span className="flex flex-col gap-0.5">
                                                {permit.plates.map((plate) => (
                                                    <span
                                                        key={plate}
                                                        translate="no"
                                                        className="text-sm font-medium whitespace-nowrap tabular-nums"
                                                    >
                                                        {plate}
                                                    </span>
                                                ))}
                                            </span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <DualDate
                                                date={permit.valid_from}
                                            />
                                            <span className="text-muted-foreground">
                                                {' – '}
                                            </span>
                                            <DualDate date={permit.valid_to} />
                                        </Td>
                                        <Td>
                                            <StatusBadge
                                                status={permit.status}
                                            />
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {permit.created_at && (
                                                <DualDate
                                                    date={permit.created_at}
                                                />
                                            )}
                                        </Td>
                                        <Td align="right">
                                            {permit.href && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link href={permit.href}>
                                                        {t('register.open')}
                                                        <ChevronRight aria-hidden="true" />
                                                    </Link>
                                                </Button>
                                            )}
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
