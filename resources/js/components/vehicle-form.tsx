import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PlateInput } from '@/components/plate-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { index } from '@/routes/vehicles';
import type { PlateRegionOption, VehicleType } from '@/types';

export type VehicleFormData = {
    plate: string;
    vehicle_type_id: string;
    owner_name: string;
};

/**
 * The fields shared by registering and editing a vehicle.
 */
export function VehicleForm({
    form,
    onSubmit,
    vehicleTypes,
    plateRegions,
}: {
    form: InertiaFormProps<VehicleFormData>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
}) {
    const { t } = useTranslation();

    return (
        <form
            onSubmit={onSubmit}
            noValidate
            className="max-w-2xl space-y-7 rounded-3xl border border-border bg-card p-6 sm:p-8"
        >
            <PlateInput
                id="plate"
                value={form.data.plate}
                regions={plateRegions}
                error={form.errors.plate}
                onChange={(plate) => form.setData('plate', plate)}
            />

            <FormField
                id="vehicle_type_id"
                label={t('vehicles.fields.vehicle_type')}
                error={form.errors.vehicle_type_id}
            >
                {(control) => (
                    <NativeSelect
                        {...control}
                        value={form.data.vehicle_type_id}
                        onChange={(event) =>
                            form.setData('vehicle_type_id', event.target.value)
                        }
                    >
                        <option value="">{t('vehicles.choose_type')}</option>
                        {vehicleTypes.map((type) => (
                            <option key={type.id} value={type.id}>
                                {type.name}
                            </option>
                        ))}
                    </NativeSelect>
                )}
            </FormField>

            <FormField
                id="owner_name"
                label={t('vehicles.fields.owner_name')}
                error={form.errors.owner_name}
            >
                {(control) => (
                    <Input
                        {...control}
                        value={form.data.owner_name}
                        autoComplete="off"
                        maxLength={255}
                        onChange={(event) =>
                            form.setData('owner_name', event.target.value)
                        }
                    />
                )}
            </FormField>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button asChild variant="outline" size="lg">
                    <Link href={index()}>{t('vehicles.actions.cancel')}</Link>
                </Button>
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {t('vehicles.actions.save')}
                </Button>
            </div>
        </form>
    );
}
