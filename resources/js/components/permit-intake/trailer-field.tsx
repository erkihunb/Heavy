import { Plus, X } from 'lucide-react';
import { PlateInput } from '@/components/plate-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import type { PlateRegionOption } from '@/types';

/**
 * The "+ Add trailer" button, kept at the end of the vehicle's row.
 */
export function AddTrailerButton({ onClick }: { onClick: () => void }) {
    const { t } = useTranslation();

    return (
        <Button type="button" variant="outline" onClick={onClick}>
            <Plus aria-hidden className="size-4" />
            {t('applicants.intake.add_trailer')}
        </Button>
    );
}

/**
 * The "+ Add vehicle" button, at the end of the last vehicle's row.
 */
export function AddVehicleButton({
    onClick,
    disabled = false,
}: {
    onClick: () => void;
    disabled?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Button
            type="button"
            variant="outline"
            onClick={onClick}
            disabled={disabled}
            title={disabled ? undefined : t('letters.intake.add')}
        >
            <Plus aria-hidden className="size-4" />
            {t('letters.intake.add')}
        </Button>
    );
}

/**
 * A vehicle's trailer, once "+ Add trailer" is pressed: the trailer's plate
 * row, lined up under the vehicle's, with a button to take it away again.
 */
export function TrailerField({
    id,
    value,
    regions,
    error,
    trailerName,
    onChange,
    onRemove,
}: {
    id: string;
    value: string;
    regions: PlateRegionOption[];
    error?: string;
    trailerName: string;
    onChange: (plate: string) => void;
    onRemove: () => void;
}) {
    const { t } = useTranslation();

    return (
        <PlateInput
            id={id}
            label={t('applicants.intake.fields.trailer_plate')}
            value={value}
            regions={regions}
            error={error}
            onChange={onChange}
            plain
            leading={
                <>
                    <span className="text-eyebrow text-muted-foreground">
                        {t('applicants.intake.fields.trailer_plate')}
                    </span>
                    <Input
                        value={trailerName}
                        readOnly
                        disabled
                        tabIndex={-1}
                    />
                </>
            }
            trailing={
                <Button
                    type="button"
                    variant="ghost"
                    aria-label={t('applicants.intake.remove_trailer')}
                    title={t('applicants.intake.remove_trailer')}
                    onClick={onRemove}
                >
                    <X aria-hidden className="size-4" />
                </Button>
            }
        />
    );
}
