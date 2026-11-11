import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
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

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
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

                {types.length === 0 ? (
                    <p className="text-muted-foreground">
                        {t('admin.vehicle_types.empty')}
                    </p>
                ) : (
                    <ul className="grid gap-3">
                        {types.map((type) => (
                            <li
                                key={type.id}
                                className="grid gap-3 rounded-2xl border border-border bg-card p-4 sm:p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                            >
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                                    <span lang="am" className="font-bold">
                                        {type.name_am}
                                    </span>
                                    <span lang="en">{type.name_en}</span>
                                    <code
                                        translate="no"
                                        className="rounded bg-muted px-1.5 py-0.5 text-sm"
                                    >
                                        {type.code}
                                    </code>
                                    <span
                                        className={cn(
                                            'rounded-full px-2.5 py-0.5 text-xs font-bold',
                                            type.is_active
                                                ? 'bg-success text-success-foreground'
                                                : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {type.is_active
                                            ? t('admin.vehicle_types.active')
                                            : t('admin.vehicle_types.inactive')}
                                    </span>
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {t('admin.vehicle_types.vehicles', {
                                            count: type.vehicles,
                                        })}
                                    </span>
                                </div>
                                <Button asChild variant="outline">
                                    <Link
                                        href={edit({ vehicle_type: type.id })}
                                    >
                                        <Pencil aria-hidden="true" />
                                        {t('permits.actions.edit')}
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
