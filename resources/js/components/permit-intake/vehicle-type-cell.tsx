import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/ui/native-select';
import { useTranslation } from '@/lib/i18n';
import type { VehicleType } from '@/types';

/**
 * The vehicle type, as the first cell of the plate's row (PlateInput's
 * `leading`): labelled like the plate's parts so the four line up.
 */
export function VehicleTypeCell({
    id,
    value,
    vehicleTypes,
    error,
    onChange,
}: {
    id: string;
    value: string;
    vehicleTypes: VehicleType[];
    error?: string;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <>
            <label htmlFor={id} className="text-eyebrow text-muted-foreground">
                {t('applicants.intake.fields.vehicle_type')}
            </label>
            <NativeSelect
                id={id}
                value={value}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${id}-error` : undefined}
                onChange={(event) => onChange(event.target.value)}
            >
                <option value="">{t('vehicles.choose_type')}</option>
                {vehicleTypes.map((type) => (
                    <option key={type.id} value={type.id}>
                        {type.name}
                    </option>
                ))}
            </NativeSelect>
            <InputError id={`${id}-error`} message={error} />
        </>
    );
}
