import { useState } from 'react';
import InputError from '@/components/input-error';
import {
    OTHER_TYPE,
    TRAILER_TYPE,
    errorsOf,
} from '@/components/permit-intake/form';
import type { IntakeForm, StepState } from '@/components/permit-intake/form';
import {
    MoreVehicles,
    emptyMoreVehicle,
} from '@/components/permit-intake/more-vehicles';
import { Step } from '@/components/permit-intake/step';
import { intakeTextField } from '@/components/permit-intake/text-field';
import {
    AddTrailerButton,
    AddVehicleButton,
    TrailerField,
} from '@/components/permit-intake/trailer-field';
import { VehicleTypeCell } from '@/components/permit-intake/vehicle-type-cell';
import { PlateInput } from '@/components/plate-input';
import { useTranslation } from '@/lib/i18n';
import type { PlateRegionOption, VehicleType } from '@/types';

/**
 * 2. The vehicle — type and plate in one row, with "+ Add trailer" and
 * "+ Add vehicle" at its end — its trailer if any, and the other vehicles
 * of the same letter.
 */
export function VehicleStep({
    form,
    state,
    vehicleTypes,
    plateRegions,
    typeCode,
    canTow,
    maxLetterVehicles,
}: {
    form: IntakeForm;
    state: StepState;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    typeCode: string | undefined;
    canTow: boolean;
    maxLetterVehicles: number;
}) {
    const { t } = useTranslation();
    const trailerName =
        vehicleTypes.find((type) => type.code === TRAILER_TYPE)?.name ?? '';
    const { data } = form;
    const errors = errorsOf(form);
    const text = intakeTextField(form);
    const [trailerOpen, setTrailerOpen] = useState(false);
    const showTrailer =
        canTow &&
        (trailerOpen ||
            data.trailer_plate !== '' ||
            errors['vehicles.1.plate'] !== undefined);
    const letterOpen = maxLetterVehicles > 1;
    const addVehicle = () =>
        form.setData('more', [...data.more, emptyMoreVehicle()]);

    return (
        <Step
            id="vehicle"
            number={2}
            title={t('applicants.intake.sections.vehicle.title')}
            state={state}
        >
            <div className="space-y-5">
                <PlateInput
                    id="plate"
                    label={t('applicants.intake.fields.plate')}
                    value={data.plate}
                    regions={plateRegions}
                    error={errors['vehicles.0.plate']}
                    onChange={(plate) => form.setData('plate', plate)}
                    plain
                    leading={
                        <VehicleTypeCell
                            id="vehicle_type_id"
                            value={data.vehicle_type_id}
                            vehicleTypes={vehicleTypes}
                            error={errors['vehicles.0.vehicle_type_id']}
                            onChange={(value) =>
                                form.setData('vehicle_type_id', value)
                            }
                        />
                    }
                    trailing={
                        <>
                            {canTow && !showTrailer && (
                                <AddTrailerButton
                                    onClick={() => setTrailerOpen(true)}
                                />
                            )}
                            {letterOpen && data.more.length === 0 && (
                                <AddVehicleButton onClick={addVehicle} />
                            )}
                        </>
                    }
                />
                {typeCode === OTHER_TYPE &&
                    text('type_detail', t('applicants.intake.type_detail'), {
                        maxLength: 100,
                        autoComplete: 'off',
                        error: errors['vehicles.0.type_detail'],
                    })}
                {showTrailer && (
                    <TrailerField
                        id="trailer_plate"
                        value={data.trailer_plate}
                        regions={plateRegions}
                        error={errors['vehicles.1.plate']}
                        trailerName={trailerName}
                        onChange={(plate) =>
                            form.setData('trailer_plate', plate)
                        }
                        onRemove={() => {
                            form.setData('trailer_plate', '');
                            setTrailerOpen(false);
                        }}
                    />
                )}
                <InputError message={errors.vehicles} />
                {letterOpen && (
                    <MoreVehicles
                        form={form}
                        vehicleTypes={vehicleTypes}
                        plateRegions={plateRegions}
                        canTow={canTow}
                        maxVehicles={maxLetterVehicles}
                        addVehicle={addVehicle}
                    />
                )}
            </div>
        </Step>
    );
}
