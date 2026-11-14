import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { OTHER_TYPE, errorsOf } from '@/components/permit-intake/form';
import type { IntakeForm, StepState } from '@/components/permit-intake/form';
import { Step } from '@/components/permit-intake/step';
import { intakeTextField } from '@/components/permit-intake/text-field';
import { PlateInput } from '@/components/plate-input';
import { NativeSelect } from '@/components/ui/native-select';
import { useTranslation } from '@/lib/i18n';
import type { PlateRegionOption, VehicleType } from '@/types';

/**
 * 2. The vehicle, and its trailer if any.
 */
export function VehicleStep({
    form,
    state,
    vehicleTypes,
    plateRegions,
    existingMode,
    typeCode,
    canTow,
}: {
    form: IntakeForm;
    state: StepState;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    existingMode: boolean;
    typeCode: string | undefined;
    canTow: boolean;
}) {
    const { t } = useTranslation();
    const { data } = form;
    const errors = errorsOf(form);
    const text = intakeTextField(form);

    return (
        <Step
            id="vehicle"
            number={2}
            title={t('applicants.intake.sections.vehicle.title')}
            description={t('applicants.intake.sections.vehicle.intro')}
            state={state}
        >
            <div className="space-y-5">
                {existingMode && (
                    <p className="rounded-md bg-muted px-4 py-3 text-sm text-muted-foreground">
                        {t('applicants.intake.existing_vehicle_hint')}
                    </p>
                )}
                <div className="grid gap-5 @2xl:grid-cols-2">
                    {text('owner_name', t('applicants.intake.fields.owner'), {
                        maxLength: 255,
                        autoComplete: 'off',
                        error: errors['vehicles.0.owner_name'],
                    })}
                    <FormField
                        id="vehicle_type_id"
                        label={t('applicants.intake.fields.vehicle_type')}
                        error={errors['vehicles.0.vehicle_type_id']}
                    >
                        {(control) => (
                            <NativeSelect
                                {...control}
                                value={data.vehicle_type_id}
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
                    {typeCode === OTHER_TYPE && (
                        <div className="md:col-span-2">
                            {text(
                                'type_detail',
                                t('applicants.intake.type_detail'),
                                {
                                    maxLength: 100,
                                    autoComplete: 'off',
                                    error: errors['vehicles.0.type_detail'],
                                },
                            )}
                        </div>
                    )}
                </div>
                <PlateInput
                    id="plate"
                    label={t('applicants.intake.fields.plate')}
                    value={data.plate}
                    regions={plateRegions}
                    error={errors['vehicles.0.plate']}
                    onChange={(plate) => form.setData('plate', plate)}
                />
                {canTow && (
                    <PlateInput
                        id="trailer_plate"
                        label={t('applicants.intake.fields.trailer_plate')}
                        value={data.trailer_plate}
                        regions={plateRegions}
                        error={errors['vehicles.1.plate']}
                        onChange={(plate) =>
                            form.setData('trailer_plate', plate)
                        }
                    />
                )}
                <InputError message={errors.vehicles} />
            </div>
        </Step>
    );
}
