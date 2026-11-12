import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Globe, Plus, Search, Store } from 'lucide-react';
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
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show } from '@/routes/admin/applicants';
import { create } from '@/routes/admin/permits';
import type {
    BreadcrumbItem,
    DualDate as DualDateValue,
    Paginated,
} from '@/types';

/** Mirrors App\Http\Controllers\Admin\ApplicantController::index(). */
type Applicant = {
    id: number;
    name: string;
    email: string;
    organization: { name: string; tin: string | null; phone: string } | null;
    registered_by: string | null;
    created_at: DualDateValue | null;
};

type Props = {
    applicants: Paginated<Applicant>;
    filters: { search: string; source: string };
};

export default function ApplicantsIndex({ applicants, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
    ];

    const filter = (next: { search?: string; source?: string }) => {
        const query = { ...filters, ...next };

        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries(query).filter(([, value]) => value !== ''),
            ),
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        filter({ search: search.trim() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('applicants.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('applicants.title')}
                    description={t('applicants.intro')}
                    action={
                        <Button asChild size="lg" className="w-full sm:w-auto">
                            <Link href={create()}>
                                <Plus aria-hidden="true" />
                                {t('applicants.new')}
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-end gap-4">
                    <form
                        role="search"
                        onSubmit={submitSearch}
                        className="flex min-w-64 flex-1 items-end gap-2"
                    >
                        <div className="grid flex-1 gap-1.5">
                            <Label htmlFor="search">
                                {t('applicants.search')}
                            </Label>
                            <Input
                                id="search"
                                type="search"
                                value={search}
                                placeholder={t('applicants.search_placeholder')}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            <Search aria-hidden="true" />
                            {t('applicants.search')}
                        </Button>
                    </form>

                    <div className="grid w-full gap-1.5 sm:w-56">
                        <Label htmlFor="source">{t('applicants.source')}</Label>
                        <NativeSelect
                            id="source"
                            value={filters.source}
                            onChange={(event) =>
                                filter({ source: event.target.value })
                            }
                        >
                            <option value="">
                                {t('applicants.any_source')}
                            </option>
                            <option value="office">
                                {t('applicants.at_office')}
                            </option>
                            <option value="online">
                                {t('applicants.online')}
                            </option>
                        </NativeSelect>
                    </div>
                </div>

                <TableCard>
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('applicants.fields.contact_name')}</Th>
                                <Th>{t('applicants.organization')}</Th>
                                <Th>{t('applicants.source')}</Th>
                                <Th>{t('applicants.registered')}</Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {applicants.data.length === 0 ? (
                                <EmptyRow colSpan={5}>
                                    {t('applicants.empty')}
                                </EmptyRow>
                            ) : (
                                applicants.data.map((applicant) => (
                                    <Tr key={applicant.id}>
                                        <Td>
                                            <span className="block font-medium">
                                                {applicant.name}
                                            </span>
                                            <span
                                                translate="no"
                                                className="block text-xs text-muted-foreground"
                                            >
                                                {applicant.email}
                                            </span>
                                        </Td>
                                        <Td>
                                            {applicant.organization ? (
                                                <>
                                                    <span className="block font-medium">
                                                        {
                                                            applicant
                                                                .organization
                                                                .name
                                                        }
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground tabular-nums">
                                                        {[
                                                            applicant
                                                                .organization
                                                                .tin &&
                                                                `${t('organization.fields.tin')} ${applicant.organization.tin}`,
                                                            applicant
                                                                .organization
                                                                .phone,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </span>
                                                </>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    {t(
                                                        'applicants.no_organization',
                                                    )}
                                                </span>
                                            )}
                                        </Td>
                                        <Td muted>
                                            <span className="flex items-center gap-1.5 whitespace-nowrap">
                                                {applicant.registered_by ? (
                                                    <Store
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                ) : (
                                                    <Globe
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                )}
                                                {applicant.registered_by
                                                    ? t('applicants.by_staff', {
                                                          name: applicant.registered_by,
                                                      })
                                                    : t('applicants.online')}
                                            </span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {applicant.created_at && (
                                                <DualDate
                                                    date={applicant.created_at}
                                                />
                                            )}
                                        </Td>
                                        <Td align="right">
                                            {applicant.organization && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={show(
                                                            applicant.id,
                                                        )}
                                                    >
                                                        {t('applicants.open')}
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

                <Pagination links={applicants.links} meta={applicants.meta} />
            </div>
        </AppLayout>
    );
}
