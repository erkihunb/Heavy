import { Head, Link, useForm } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { PermitForm } from '@/components/permit-form';
import type { PermitFormData, PermitLimits } from '@/components/permit-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, index, store } from '@/routes/permits';
import { create as registerVehicle } from '@/routes/vehicles';
import type { BreadcrumbItem, Vehicle } from '@/types';

type Props = {
    vehicles: Vehicle[];
    limits: PermitLimits;
};

export default function PermitsCreate({ vehicles, limits }: Props) {
    const { t } = useTranslation();

    const form = useForm<PermitFormData>({
        permit_type: '',
        primary_vehicle_id: '',
        trailer_vehicle_ids: [],
        valid_from: '',
        valid_to: '',
        reason: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('permits.title'), href: index() },
        { title: t('permits.create_title'), href: create() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('permits.create_title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader title={t('permits.create_title')} />

                {vehicles.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-input px-6 py-16 text-center">
                        <span className="flex size-16 items-center justify-center rounded-xl bg-highlight text-highlight-foreground">
                            <Truck aria-hidden="true" className="size-8" />
                        </span>
                        <h2 className="text-2xl text-display">
                            {t('permits.no_vehicles.title')}
                        </h2>
                        <p className="max-w-sm text-muted-foreground">
                            {t('permits.no_vehicles.body')}
                        </p>
                        <Button asChild size="lg">
                            <Link href={registerVehicle()}>
                                {t('vehicles.register')}
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <PermitForm
                        form={form}
                        onSubmit={submit}
                        vehicles={vehicles}
                        limits={limits}
                        cancelHref={index.url()}
                    />
                )}
            </div>
        </AppLayout>
    );
}
