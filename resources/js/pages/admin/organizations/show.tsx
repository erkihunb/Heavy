import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';
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
import { PlateChip } from '@/components/plate-chip';
import { RowActions } from '@/components/row-actions';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { destroy, edit, index, show } from '@/routes/admin/organizations';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import type { BreadcrumbItem, DualDate as DualDateValue } from '@/types';
import type { PermitStatus } from '@/types/enums';
import type { AdminOrganization } from './index';

type Props = {
    organization: AdminOrganization;
    /** The applicant accounts requests are registered under. */
    contacts: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
    }[];
    vehicles: {
        id: string;
        plate: string;
        type: string;
        owner: string;
        permits: number;
        /** Null without the permission, or once a request that has gone further carries it. */
        editUrl: string | null;
        /** Null without the permission. */
        deleteUrl: string | null;
        deletable: boolean;
    }[];
    permits: {
        id: string;
        number: string | null;
        status: PermitStatus;
        valid_from: DualDateValue;
        valid_to: DualDateValue;
    }[];
    can: { update: boolean; delete: boolean };
    /** No request names the organization, so it may be deleted. */
    deletable: boolean;
};

/**
 * One organization: its details, the applicants it is registered under, its
 * vehicles (corrected and deleted here) and its latest requests.
 */
function OrganizationShowPage({
    organization,
    contacts,
    vehicles,
    permits,
    can,
    deletable,
}: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('organizations.title'), href: index() },
        {
            title: organization.name_am,
            href: show({ organization: organization.id }),
        },
    ];

    const details: [string, string | null][] = [
        [t('organizations.fields.name_am'), organization.name_am],
        [t('organizations.fields.name_en'), organization.name_en],
        [t('organizations.fields.tin'), organization.tin],
        [t('organizations.fields.phone'), organization.phone],
        [t('organizations.fields.email'), organization.email],
        [t('organizations.fields.address'), organization.address],
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={organization.name_am} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={organization.name_am}
                    action={
                        <span className="flex flex-wrap gap-2">
                            {can.update && (
                                <Button asChild size="lg">
                                    <Link
                                        href={edit({
                                            organization: organization.id,
                                        })}
                                    >
                                        <Pencil aria-hidden="true" />
                                        {t('permits.actions.edit')}
                                    </Link>
                                </Button>
                            )}
                            <RowActions
                                name={organization.name_am}
                                editHref={null}
                                deleteUrl={
                                    can.delete
                                        ? destroy.url({
                                              organization: organization.id,
                                          })
                                        : null
                                }
                                deletable={deletable}
                            />
                        </span>
                    }
                />

                <section
                    aria-labelledby="details-title"
                    className="rounded-card border border-border bg-card p-5 sm:p-6"
                >
                    <h2 id="details-title" className="text-section-title">
                        {t('organizations.details')}
                    </h2>
                    <dl className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {details.map(([label, value]) => (
                            <div key={label} className="space-y-0.5">
                                <dt className="text-xs text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-medium break-words">
                                    {value === null || value === ''
                                        ? '—'
                                        : value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {contacts.length > 0 && (
                        <>
                            <h3 className="mt-6 text-sm font-semibold">
                                {t('organizations.contacts')}
                            </h3>
                            <ul className="mt-2 space-y-1 text-sm">
                                {contacts.map((contact) => (
                                    <li key={contact.id}>
                                        <span className="font-medium">
                                            {contact.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' · '}
                                            {contact.email}
                                            {contact.phone
                                                ? ` · ${contact.phone}`
                                                : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </section>

                <section aria-labelledby="vehicles-title" className="space-y-3">
                    <h2 id="vehicles-title" className="text-section-title">
                        {t('organizations.vehicles')}
                    </h2>
                    <TableCard toolsId="organization-vehicles">
                        <Table>
                            <THead>
                                <tr>
                                    <Th>{t('vehicles.fields.plate')}</Th>
                                    <Th>{t('vehicles.fields.vehicle_type')}</Th>
                                    <Th>{t('organizations.fields.owner')}</Th>
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
                                {vehicles.length === 0 ? (
                                    <EmptyRow colSpan={5}>
                                        {t('organizations.no_vehicles')}
                                    </EmptyRow>
                                ) : (
                                    vehicles.map((vehicle) => (
                                        <Tr key={vehicle.id}>
                                            <Td>
                                                <PlateChip
                                                    plate={vehicle.plate}
                                                />
                                            </Td>
                                            <Td>{vehicle.type}</Td>
                                            <Td>{vehicle.owner}</Td>
                                            <Td numeric>{vehicle.permits}</Td>
                                            <Td align="right">
                                                <RowActions
                                                    name={vehicle.plate}
                                                    editHref={vehicle.editUrl}
                                                    deleteUrl={
                                                        vehicle.deleteUrl
                                                    }
                                                    deletable={
                                                        vehicle.deletable
                                                    }
                                                />
                                            </Td>
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>
                </section>

                <section aria-labelledby="requests-title" className="space-y-3">
                    <h2 id="requests-title" className="text-section-title">
                        {t('organizations.requests')}
                    </h2>
                    <TableCard toolsId="organization-requests">
                        <Table>
                            <THead>
                                <tr>
                                    <Th>{t('permits.fields.permit_number')}</Th>
                                    <Th>{t('permits.fields.valid_from')}</Th>
                                    <Th>{t('permits.fields.valid_to')}</Th>
                                    <Th>{t('common.status')}</Th>
                                </tr>
                            </THead>
                            <TBody>
                                {permits.length === 0 ? (
                                    <EmptyRow colSpan={4}>
                                        {t('organizations.no_requests')}
                                    </EmptyRow>
                                ) : (
                                    permits.map((permit) => (
                                        <Tr key={permit.id}>
                                            <Td className="tabular-nums">
                                                {permit.number ?? '—'}
                                            </Td>
                                            <Td>
                                                <DualDate
                                                    date={permit.valid_from}
                                                />
                                            </Td>
                                            <Td>
                                                <DualDate
                                                    date={permit.valid_to}
                                                />
                                            </Td>
                                            <Td>
                                                <StatusBadge
                                                    status={permit.status}
                                                />
                                            </Td>
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>
                </section>

                <div>
                    <Button asChild variant="ghost">
                        <Link href={index()}>
                            <ArrowLeft aria-hidden="true" />
                            {t('organizations.title')}
                        </Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}

export default function OrganizationShow(props: Props) {
    return (
        <ConfirmProvider>
            <OrganizationShowPage {...props} />
        </ConfirmProvider>
    );
}
