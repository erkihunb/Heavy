import { Head, Link, router } from '@inertiajs/react';
import { Eye, Plus, Search } from 'lucide-react';
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
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { RowActions } from '@/components/row-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { destroy, edit, index, show } from '@/routes/admin/organizations';
import { create as registerRequest } from '@/routes/admin/permits';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import type { BreadcrumbItem, Paginated } from '@/types';

export type AdminOrganization = {
    id: number;
    name_am: string;
    name_en: string | null;
    tin: string | null;
    phone: string;
    email: string;
    address: string;
};

type Row = AdminOrganization & {
    vehicles: number;
    permits: number;
    /** What this person may do to the organization (each its own permission). */
    can: { update: boolean; delete: boolean };
};

type Props = {
    organizations: Paginated<Row>;
    filters: { search: string };
    /** May register a new organization, with its first request. */
    canRegister: boolean;
};

/**
 * The organizations requests are registered for
 * (App\Http\Controllers\Admin\OrganizationController): found, opened,
 * corrected and deleted, each by its own permission.
 */
function OrganizationsIndexPage({
    organizations,
    filters,
    canRegister,
}: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('organizations.title'), href: index() },
    ];

    const find = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            index.url(),
            search.trim() === '' ? {} : { search: search.trim() },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('organizations.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('organizations.title')}
                    description={t('organizations.intro')}
                    action={
                        canRegister && (
                            <Button
                                asChild
                                size="lg"
                                className="w-full sm:w-auto"
                            >
                                <Link href={registerRequest()}>
                                    <Plus aria-hidden="true" />
                                    {t('organizations.new')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <form
                    onSubmit={find}
                    role="search"
                    className="flex flex-col gap-2 sm:flex-row"
                >
                    <label htmlFor="organization-search" className="sr-only">
                        {t('organizations.search')}
                    </label>
                    <Input
                        id="organization-search"
                        type="search"
                        value={search}
                        placeholder={t('organizations.search')}
                        onChange={(event) => setSearch(event.target.value)}
                        className="sm:max-w-md"
                    />
                    <Button type="submit" variant="outline">
                        <Search aria-hidden="true" />
                        {t('organizations.search_button')}
                    </Button>
                </form>

                <TableCard toolsId="organizations">
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('organizations.fields.name_am')}</Th>
                                <Th>{t('organizations.fields.tin')}</Th>
                                <Th>{t('organizations.fields.phone')}</Th>
                                <Th align="right">
                                    {t('organizations.fields.vehicles')}
                                </Th>
                                <Th align="right">
                                    {t('organizations.fields.permits')}
                                </Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {organizations.data.length === 0 ? (
                                <EmptyRow colSpan={6}>
                                    {t('organizations.empty')}
                                </EmptyRow>
                            ) : (
                                organizations.data.map((organization) => (
                                    <Tr key={organization.id}>
                                        <Td lang="am" className="font-medium">
                                            <Link
                                                href={show({
                                                    organization:
                                                        organization.id,
                                                })}
                                                className="hover:underline"
                                            >
                                                {organization.name_am}
                                            </Link>
                                        </Td>
                                        <Td className="tabular-nums">
                                            {organization.tin ?? '—'}
                                        </Td>
                                        <Td className="tabular-nums">
                                            {organization.phone}
                                        </Td>
                                        <Td numeric>{organization.vehicles}</Td>
                                        <Td numeric>{organization.permits}</Td>
                                        <Td align="right">
                                            <span className="inline-flex flex-wrap justify-end gap-2">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={show({
                                                            organization:
                                                                organization.id,
                                                        })}
                                                    >
                                                        <Eye aria-hidden="true" />
                                                        {t(
                                                            'organizations.open',
                                                        )}
                                                    </Link>
                                                </Button>
                                                <RowActions
                                                    name={organization.name_am}
                                                    editHref={
                                                        organization.can.update
                                                            ? edit.url({
                                                                  organization:
                                                                      organization.id,
                                                              })
                                                            : null
                                                    }
                                                    deleteUrl={
                                                        organization.can.delete
                                                            ? destroy.url({
                                                                  organization:
                                                                      organization.id,
                                                              })
                                                            : null
                                                    }
                                                    deletable={
                                                        organization.permits ===
                                                        0
                                                    }
                                                />
                                            </span>
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>

                <Pagination
                    links={organizations.links}
                    meta={organizations.meta}
                />
            </div>
        </AppLayout>
    );
}

export default function OrganizationsIndex(props: Props) {
    return (
        <ConfirmProvider>
            <OrganizationsIndexPage {...props} />
        </ConfirmProvider>
    );
}
