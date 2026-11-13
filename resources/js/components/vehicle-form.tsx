import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { FormActions, FormSection } from '@/components/form-layout';
import { PlateInput } from '@/components/plate-input';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useTranslation } from '@/lib/i18n';
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
    cancelHref,
}: {
    form: InertiaFormProps<VehicleFormData>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    /** Where "Cancel" goes back to. */
    cancelHref: string;
}) {
    const { t } = useTranslation();

    return (
        <form onSubmit={onSubmit} noValidate className="space-y-5">
            <FormSection className="space-y-7">
                <PlateInput
                    id="plate"
                    required
                    value={form.data.plate}
                    regions={plateRegions}
                    error={form.errors.plate}
                    onChange={(plate) => form.setData('plate', plate)}
                />

                <FormField
                    id="vehicle_type_id"
                    required
                    label={t('vehicles.fields.vehicle_type')}
                    error={form.errors.vehicle_type_id}
                >
                    {(control) => (
                        <NativeSelect
                            {...control}
                            value={form.data.vehicle_type_id}
                            onChange={(event) =>
                                form.setData(
                                    'vehicle_type_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">
                                {t('vehicles.choose_type')}
                            </option>
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
                    required
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
            </FormSection>

            <FormActions
                cancelHref={cancelHref}
                cancelLabel={t('vehicles.actions.cancel')}
                submitLabel={t('vehicles.actions.save')}
                processing={form.processing}
            />
        </form>
    );
}
