import { Trash2 } from 'lucide-react';
import { FormField } from '@/components/form-field';
import {
    OTHER_TYPE,
    TRAILER_TYPE,
    errorsOf,
} from '@/components/permit-intake/form';
import type { IntakeForm, MoreVehicle } from '@/components/permit-intake/form';
import {
    AddTrailerButton,
    AddVehicleButton,
    TrailerField,
} from '@/components/permit-intake/trailer-field';
import { VehicleTypeCell } from '@/components/permit-intake/vehicle-type-cell';
import { PlateInput } from '@/components/plate-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import type { PlateRegionOption, VehicleType } from '@/types';

export const emptyMoreVehicle = (): MoreVehicle => ({
    with_trailer: false,
    vehicle_type_id: '',
    type_detail: '',
    plate: '',
    trailer_plate: '',
});

/**
 * The other vehicles the same request letter asks for, one row each — type
 * and plate, with "+ Add trailer", "+ Add vehicle" (on the last row) and a
 * button to take the vehicle out at its end. Each becomes a request (and,
 * once issued, a permit with its own certificate and QR code) of its own, on
 * the terms of step 3; the letter keeps them together.
 */
export function MoreVehicles({
    form,
    vehicleTypes,
    plateRegions,
    canTow,
    maxVehicles,
    addVehicle,
}: {
    form: IntakeForm;
    vehicleTypes: VehicleType[];
    plateRegions: PlateRegionOption[];
    canTow: boolean;
    maxVehicles: number;
    addVehicle: () => void;
}) {
    const { t } = useTranslation();
    const { data } = form;
    const errors = errorsOf(form);
    const rows = data.more;
    const full = rows.length + 1 >= maxVehicles;
    const trailerName =
        vehicleTypes.find((type) => type.code === TRAILER_TYPE)?.name ?? '';

    const update = (index: number, changes: Partial<MoreVehicle>) =>
        form.setData(
            'more',
            rows.map((row, at) =>
                at === index ? { ...row, ...changes } : row,
            ),
        );

    const remove = (index: number) =>
        form.setData(
            'more',
            rows.filter((_, at) => at !== index),
        );

    if (rows.length === 0) {
        return errors.more ? (
            <p className="text-sm text-destructive">{errors.more}</p>
        ) : null;
    }

    return (
        <section
            aria-labelledby="more-vehicles-title"
            className="space-y-4 border-t border-border pt-5"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3
                    id="more-vehicles-title"
                    className="text-sm font-semibold text-foreground"
                >
                    {t('letters.intake.title')}
                </h3>
                <span className="text-sm text-muted-foreground tabular-nums">
                    {t('letters.intake.count', { count: rows.length + 1 })}
                </span>
            </div>

            {rows.map((row, index) => {
                const prefix = `more.${index}.vehicles`;
                const number = index + 2;
                const last = index === rows.length - 1;
                const typeCode = vehicleTypes.find(
                    (type) => String(type.id) === row.vehicle_type_id,
                )?.code;
                const showTrailer =
                    canTow &&
                    (row.with_trailer ||
                        row.trailer_plate !== '' ||
                        errors[`${prefix}.1.plate`] !== undefined);

                return (
                    <fieldset
                        key={index}
                        className="space-y-3 rounded-md border border-border p-3"
                    >
                        <legend className="px-1 text-xs font-semibold text-muted-foreground">
                            {t('letters.intake.vehicle', { number })}
                        </legend>
                        <PlateInput
                            id={`more_${index}_plate`}
                            label={t('applicants.intake.fields.plate')}
                            value={row.plate}
                            regions={plateRegions}
                            error={errors[`${prefix}.0.plate`]}
                            onChange={(plate) => update(index, { plate })}
                            plain
                            leading={
                                <VehicleTypeCell
                                    id={`more_${index}_type`}
                                    value={row.vehicle_type_id}
                                    vehicleTypes={vehicleTypes}
                                    error={
                                        errors[`${prefix}.0.vehicle_type_id`]
                                    }
                                    onChange={(value) =>
                                        update(index, {
                                            vehicle_type_id: value,
                                        })
                                    }
                                />
                            }
                            trailing={
                                <>
                                    {canTow && !showTrailer && (
                                        <AddTrailerButton
                                            onClick={() =>
                                                update(index, {
                                                    with_trailer: true,
                                                })
                                            }
                                        />
                                    )}
                                    {last && (
                                        <AddVehicleButton
                                            onClick={addVehicle}
                                            disabled={full}
                                        />
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => remove(index)}
                                        aria-label={t('letters.intake.remove', {
                                            number,
                                        })}
                                        title={t('letters.intake.remove', {
                                            number,
                                        })}
                                    >
                                        <Trash2
                                            aria-hidden
                                            className="size-4"
                                        />
                                    </Button>
                                </>
                            }
                        />
                        {typeCode === OTHER_TYPE && (
                            <FormField
                                id={`more_${index}_detail`}
                                label={t('applicants.intake.type_detail')}
                                error={errors[`${prefix}.0.type_detail`]}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        maxLength={100}
                                        autoComplete="off"
                                        value={row.type_detail}
                                        onChange={(event) =>
                                            update(index, {
                                                type_detail: event.target.value,
                                            })
                                        }
                                    />
                                )}
                            </FormField>
                        )}
                        {showTrailer && (
                            <TrailerField
                                id={`more_${index}_trailer`}
                                value={row.trailer_plate}
                                regions={plateRegions}
                                error={errors[`${prefix}.1.plate`]}
                                trailerName={trailerName}
                                onChange={(plate) =>
                                    update(index, { trailer_plate: plate })
                                }
                                onRemove={() =>
                                    update(index, {
                                        trailer_plate: '',
                                        with_trailer: false,
                                    })
                                }
                            />
                        )}
                    </fieldset>
                );
            })}

            {full && (
                <p className="text-xs text-muted-foreground">
                    {t('letters.intake.limit', { max: maxVehicles })}
                </p>
            )}
            {errors.more && (
                <p className="text-sm text-destructive">{errors.more}</p>
            )}
        </section>
    );
}
