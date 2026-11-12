import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    ChevronRight,
    FilePlus2,
    FileText,
    Plus,
    Truck,
} from 'lucide-react';
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
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show } from '@/routes/admin/applicants';
import {
    create as createPermit,
    show as showPermit,
} from '@/routes/admin/applicants/permits';
import { create as createVehicle } from '@/routes/admin/applicants/vehicles';
import type { BreadcrumbItem, Permit, Vehicle } from '@/types';

/** Mirrors App\Http\Controllers\Admin\WalkInController::show(). */
type Props = {
    applicant: {
        id: number;
        name: string;
        email: string;
        registered_by: string | null;
    };
    organization: {
        name_am: string;
        name_en: string | null;
        tin: string | null;
        phone: string;
        email: string;
        address: string;
    };
    vehicles: Vehicle[];
    permits: Permit[];
};

/**
 * One walk-in applicant (Registrar): who they are, their organization, the
 * vehicles registered for them and their applications — with the next
 * steps, add a vehicle and start an application, one click away.
 */
export default function ApplicantShow({
    applicant,
    organization,
    vehicles,
    permits,
}: Props) {
    const { t, locale } = useTranslation();
    const name =
        locale === 'en' && organization.name_en
            ? organization.name_en
            : organization.name_am;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
        { title: name, href: show(applicant.id) },
    ];

    const details: [string, string | null][] = [
        [t('applicants.fields.contact_name'), applicant.name],
        [t('applicants.fields.contact_email'), applicant.email],
        [t('organization.fields.tin'), organization.tin],
        [t('organization.fields.phone'), organization.phone],
        [t('organization.fields.email'), organization.email],
        [t('organization.fields.address'), organization.address],
        [
            t('applicants.source'),
            applicant.registered_by
                ? t('applicants.by_staff', { name: applicant.registered_by })
                : t('applicants.online'),
        ],
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={name} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={name}
                    description={t('applicants.show_intro')}
                    action={
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Button asChild variant="outline" size="lg">
                                <Link href={createVehicle(applicant.id)}>
                                    <Plus aria-hidden="true" />
                                    {t('vehicles.register')}
                                </Link>
                            </Button>
                            {vehicles.length > 0 && (
                                <Button asChild size="lg">
                                    <Link href={createPermit(applicant.id)}>
                                        <FilePlus2 aria-hidden="true" />
                                        {t('permits.new')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                <SectionCard
                    id="organization"
                    icon={Building2}
                    title={t('applicants.organization')}
                >
                    <dl className="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                        {details.map(([label, value]) => (
                            <div key={label} className="min-w-0">
                                <dt className="text-eyebrow text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="mt-0.5 font-medium break-words">
                                    {value || '—'}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </SectionCard>

                <SectionCard
                    id="vehicles"
                    icon={Truck}
                    title={t('vehicles.title')}
                    description={t('vehicles.count', {
                        count: String(vehicles.length),
                    })}
                    flush
                >
                    <TableCard className="rounded-none border-0">
                        <Table>
                            <THead>
                                <tr>
                                    <Th>{t('vehicles.fields.plate')}</Th>
                                    <Th>{t('vehicles.fields.vehicle_type')}</Th>
                                    <Th>{t('vehicles.fields.owner_name')}</Th>
                                    <Th>{t('vehicles.fields.registered')}</Th>
                                </tr>
                            </THead>
                            <TBody>
                                {vehicles.length === 0 ? (
                                    <EmptyRow colSpan={4}>
                                        {t('applicants.no_vehicles')}
                                    </EmptyRow>
                                ) : (
                                    vehicles.map((vehicle) => (
                                        <Tr key={vehicle.id}>
                                            <Td>
                                                <span
                                                    translate="no"
                                                    className="font-semibold tracking-wide tabular-nums"
                                                >
                                                    {vehicle.plate}
                                                </span>
                                            </Td>
                                            <Td>{vehicle.vehicle_type.name}</Td>
                                            <Td muted>{vehicle.owner_name}</Td>
                                            <Td className="whitespace-nowrap">
                                                <DualDate
                                                    date={vehicle.registered_at}
                                                />
                                            </Td>
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>
                </SectionCard>

                <SectionCard
                    id="permits"
                    icon={FileText}
                    title={t('permits.title')}
                    flush
                >
                    <TableCard className="rounded-none border-0">
                        <Table>
                            <THead>
                                <tr>
                                    <Th>{t('permits.fields.permit_number')}</Th>
                                    <Th>{t('permits.fields.permit_type')}</Th>
                                    <Th>{t('permits.fields.validity')}</Th>
                                    <Th>{t('permits.fields.status')}</Th>
                                    <Th align="right">
                                        <span className="sr-only">
                                            {t('common.actions')}
                                        </span>
                                    </Th>
                                </tr>
                            </THead>
                            <TBody>
                                {permits.length === 0 ? (
                                    <EmptyRow colSpan={5}>
                                        {vehicles.length === 0
                                            ? t('applicants.vehicles_first')
                                            : t('applicants.no_permits')}
                                    </EmptyRow>
                                ) : (
                                    permits.map((permit) => (
                                        <Tr key={permit.id}>
                                            <Td>
                                                <span
                                                    translate="no"
                                                    className="font-medium tabular-nums"
                                                >
                                                    {permit.permit_number ??
                                                        t('applicants.draft')}
                                                </span>
                                            </Td>
                                            <Td>
                                                {t(
                                                    `permits.types.${permit.permit_type}`,
                                                )}
                                            </Td>
                                            <Td className="whitespace-nowrap">
                                                <DualDate
                                                    date={permit.valid_from}
                                                />
                                                <span className="text-muted-foreground">
                                                    {' – '}
                                                </span>
                                                <DualDate
                                                    date={permit.valid_to}
                                                />
                                            </Td>
                                            <Td>
                                                <StatusBadge
                                                    status={
                                                        permit.effective_status
                                                    }
                                                />
                                            </Td>
                                            <Td align="right">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={showPermit({
                                                            applicant:
                                                                applicant.id,
                                                            permit: permit.id,
                                                        })}
                                                    >
                                                        {t('applicants.open')}
                                                        <ChevronRight aria-hidden="true" />
                                                    </Link>
                                                </Button>
                                            </Td>
                                        </Tr>
                                    ))
                                )}
                            </TBody>
                        </Table>
                    </TableCard>
                </SectionCard>
            </div>
        </AppLayout>
    );
}
