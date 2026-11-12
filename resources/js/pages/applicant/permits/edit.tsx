import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { PermitForm } from '@/components/permit-form';
import type { PermitFormData, PermitLimits } from '@/components/permit-form';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { edit, index, show, update } from '@/routes/permits';
import type { BreadcrumbItem, Permit, Vehicle } from '@/types';

type Props = {
    permit: Permit;
    vehicles: Vehicle[];
    limits: PermitLimits;
    branches: { id: number; code: string; name: string }[];
};

export default function PermitsEdit({
    permit,
    vehicles,
    limits,
    branches,
}: Props) {
    const { t } = useTranslation();
    const permitVehicles = permit.vehicles ?? [];

    const form = useForm<PermitFormData>({
        permit_type: permit.permit_type,
        primary_vehicle_id:
            permitVehicles.find((vehicle) => vehicle.role === 'primary')?.id ??
            '',
        trailer_vehicle_ids: permitVehicles
            .filter((vehicle) => vehicle.role === 'trailer')
            .map((vehicle) => vehicle.id),
        valid_from: permit.valid_from.gregorian,
        valid_to: permit.valid_to.gregorian,
        reason: permit.reason,
        any_time: permit.hours === null,
        move_from: permit.hours?.from ?? '',
        move_to: permit.hours?.to ?? '',
        branch_id: permit.branch ? String(permit.branch.id) : '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url({ permit: permit.id }), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('permits.title'), href: index() },
        {
            title: t('permits.show_title'),
            href: show({ permit: permit.id }),
        },
        { title: t('permits.edit_title'), href: edit({ permit: permit.id }) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.edit_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader title={t('permits.edit_title')} />
                <PermitForm
                    form={form}
                    onSubmit={submit}
                    vehicles={vehicles}
                    limits={limits}
                    branches={branches}
                    cancelHref={show.url({ permit: permit.id })}
                />
            </div>
        </AppLayout>
    );
}
