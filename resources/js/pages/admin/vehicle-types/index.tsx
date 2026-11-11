import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
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
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { create, edit, index } from '@/routes/admin/vehicle-types';
import type { BreadcrumbItem } from '@/types';
import type { AdminVehicleType } from '@/types/admin';

type Props = {
    types: (AdminVehicleType & { vehicles: number })[];
};

export default function VehicleTypesIndex({ types }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.vehicle_types.title'), href: index() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.vehicle_types.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('admin.vehicle_types.title')}
                    description={t('admin.vehicle_types.intro')}
                    action={
                        <Button asChild size="lg" className="w-full sm:w-auto">
                            <Link href={create()}>
                                <Plus aria-hidden="true" />
                                {t('admin.vehicle_types.new')}
                            </Link>
                        </Button>
                    }
                />

                <TableCard>
                    <Table>
                        <THead>
                            <tr>
                                <Th>
                                    {t('admin.vehicle_types.fields.name_am')}
                                </Th>
                                <Th>
                                    {t('admin.vehicle_types.fields.name_en')}
                                </Th>
                                <Th>{t('admin.vehicle_types.fields.code')}</Th>
                                <Th align="right">
                                    {t('admin.vehicle_types.fields.sort_order')}
                                </Th>
                                <Th align="right">{t('nav.vehicles')}</Th>
                                <Th>{t('common.status')}</Th>
                                <Th align="right">
                                    <span className="sr-only">
                                        {t('common.actions')}
                                    </span>
                                </Th>
                            </tr>
                        </THead>
                        <TBody>
                            {types.length === 0 ? (
                                <EmptyRow colSpan={7}>
                                    {t('admin.vehicle_types.empty')}
                                </EmptyRow>
                            ) : (
                                types.map((type) => (
                                    <Tr key={type.id}>
                                        <Td lang="am" className="font-medium">
                                            {type.name_am}
                                        </Td>
                                        <Td lang="en">{type.name_en}</Td>
                                        <Td>
                                            <code
                                                translate="no"
                                                className="rounded-xs bg-muted px-1.5 py-0.5 text-xs"
                                            >
                                                {type.code}
                                            </code>
                                        </Td>
                                        <Td numeric>{type.sort_order}</Td>
                                        <Td numeric>{type.vehicles}</Td>
                                        <Td>
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                                    type.is_active
                                                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900'
                                                        : 'bg-muted text-muted-foreground ring-border',
                                                )}
                                            >
                                                {type.is_active
                                                    ? t(
                                                          'admin.vehicle_types.active',
                                                      )
                                                    : t(
                                                          'admin.vehicle_types.inactive',
                                                      )}
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
                                                        vehicle_type: type.id,
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
            </div>
        </AppLayout>
    );
}
