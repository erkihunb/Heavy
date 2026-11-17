import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormSection } from '@/components/form-layout';
import PageHeader from '@/shell/Components/PageHeader';
import {
    Button,
    FormField,
    Input,
    Select,
    buttonClassName,
} from '@/shell/Components/ui';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

/**
 * Registering an institution vehicle, or changing one: its plate, type and
 * model. While a permit printed with the plate is in force the plate stays
 * as it is, so the paper and the register keep agreeing.
 */
export default function InstitutionVehicleForm({
    vehicle,
    vehicleTypes,
    urls,
}: {
    vehicle: {
        id: string;
        plate: string;
        vehicle_type_id: number;
        plate_locked: boolean;
    } | null;
    vehicleTypes: { id: number; name: string }[];
    urls: { index: string; save: string };
}) {
    const { t } = useLocale();
    const form = useForm({
        plate: vehicle?.plate ?? '',
        // A new vehicle has no type until one is chosen: nothing is preselected.
        vehicle_type_id: vehicle ? String(vehicle.vehicle_type_id) : '',
    });
    const title = vehicle
        ? t('institution.vehicle_edit_title').replace(':plate', vehicle.plate)
        : t('institution.vehicle_new_title');

    function submit(event: FormEvent) {
        event.preventDefault();

        if (vehicle) {
            form.put(urls.save, { preserveScroll: true });
        } else {
            form.post(urls.save, { preserveScroll: true });
        }
    }

    return (
        <AuthenticatedLayout
            contentWidth="form"
            header={
                <PageHeader
                    backHref={urls.index}
                    title={title}
                    description={t('institution.vehicle_form_intro')}
                />
            }
        >
            <Head title={title} />

            <form onSubmit={submit} noValidate className="space-y-5">
                <FormSection title={t('institution.columns.vehicle')}>
                    <div className="grid items-start gap-5 md:grid-cols-2">
                        <FormField
                            label={t('institution.fields.plate')}
                            required
                            error={form.errors.plate}
                            description={
                                vehicle?.plate_locked
                                    ? t('institution.plate_locked_help')
                                    : undefined
                            }
                        >
                            {({ id, describedBy, invalid }) => (
                                <Input
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    autoComplete="off"
                                    autoFocus={!vehicle}
                                    readOnly={vehicle?.plate_locked}
                                    disabled={vehicle?.plate_locked}
                                    className="font-mono"
                                    placeholder={t(
                                        'institution.fields.plate_placeholder',
                                    )}
                                    value={form.data.plate}
                                    onChange={(event) =>
                                        form.setData(
                                            'plate',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>
                        <FormField
                            label={t('institution.fields.vehicle_type')}
                            required
                            error={form.errors.vehicle_type_id}
                        >
                            {({ id, describedBy, invalid }) => (
                                <Select
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    value={form.data.vehicle_type_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'vehicle_type_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="" disabled>
                                        {t('institution.choose_type')}
                                    </option>
                                    {vehicleTypes.map((type) => (
                                        <option
                                            key={type.id}
                                            value={String(type.id)}
                                        >
                                            {type.name}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </FormField>
                    </div>
                </FormSection>

                <div className="flex justify-end gap-2">
                    <Link
                        href={urls.index}
                        className={buttonClassName({ variant: 'ghost' })}
                    >
                        {t('shell.common.cancel')}
                    </Link>
                    <Button
                        type="submit"
                        loading={form.processing}
                        disabled={
                            form.data.plate.trim() === '' ||
                            form.data.vehicle_type_id === '' ||
                            (vehicle !== null && !form.isDirty)
                        }
                    >
                        {vehicle
                            ? t('institution.vehicle_save')
                            : t('institution.register_vehicle')}
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
