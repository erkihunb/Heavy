import InputError from '@/components/input-error';
import { PlateChip } from '@/components/plate-chip';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useTranslation } from '@/lib/i18n';
import type { PlateRegionOption } from '@/types';

type Parts = { region: string; code: string; number: string };

function split(value: string): Parts {
    const [region = '', code = '', ...rest] = value.split('-');

    return { region, code, number: rest.join('') };
}

function join({ region, code, number }: Parts): string {
    return region === '' && code === '' && number === ''
        ? ''
        : `${region}-${code}-${number}`;
}

/**
 * Enters a plate number as its three parts — region, code, number — so the
 * region is a controlled choice and the shape is right before it is sent.
 * The value is the plate as "region-code-number"; the server remains the
 * authority on validity and normalisation.
 *
 * The inputs have no maxLength on purpose: the browser would count spaces and
 * dashes in pasted text and cut the plate short before it is cleaned up.
 */
export function PlateInput({
    id,
    value,
    onChange,
    regions,
    error,
    disabled = false,
    label,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    regions: PlateRegionOption[];
    error?: string;
    disabled?: boolean;
    /** The heading of the three parts; "Plate number" unless given. */
    label?: string;
}) {
    const { t } = useTranslation();
    const parts = split(value);
    const update = (changed: Partial<Parts>) =>
        onChange(join({ ...parts, ...changed }));

    const complete =
        parts.region !== '' && parts.code !== '' && parts.number.length >= 4;
    const hintId = `${id}-hint`;
    const errorId = error ? `${id}-error` : undefined;
    const invalid = error ? true : undefined;

    return (
        <fieldset
            aria-describedby={[hintId, errorId].filter(Boolean).join(' ')}
            className="grid gap-3"
            disabled={disabled}
        >
            <legend className="mb-2 text-sm leading-none font-medium">
                {label ?? t('vehicles.fields.plate')}
            </legend>

            <div className="grid grid-cols-[minmax(0,1.3fr)_minmax(0,0.7fr)] gap-3 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,0.6fr)_minmax(0,1.4fr)]">
                <div className="grid gap-1.5">
                    <label
                        htmlFor={`${id}-region`}
                        className="text-eyebrow text-muted-foreground"
                    >
                        {t('vehicles.fields.region')}
                    </label>
                    <NativeSelect
                        id={`${id}-region`}
                        value={parts.region}
                        aria-invalid={invalid}
                        onChange={(event) =>
                            update({ region: event.target.value })
                        }
                    >
                        <option value="">—</option>
                        {regions.map((region) => (
                            <option key={region.value} value={region.value}>
                                {region.value} · {region.latin}
                            </option>
                        ))}
                    </NativeSelect>
                </div>

                <div className="grid gap-1.5">
                    <label
                        htmlFor={`${id}-code`}
                        className="text-eyebrow text-muted-foreground"
                    >
                        {t('vehicles.fields.code')}
                    </label>
                    <Input
                        id={`${id}-code`}
                        value={parts.code}
                        inputMode="numeric"
                        autoComplete="off"
                        placeholder="03"
                        aria-invalid={invalid}
                        className="text-center font-bold tracking-widest tabular-nums placeholder:font-normal placeholder:tracking-normal placeholder:opacity-60"
                        onChange={(event) =>
                            update({
                                code: event.target.value
                                    .replace(/\D/g, '')
                                    .slice(0, 2),
                            })
                        }
                    />
                </div>

                <div className="col-span-2 grid gap-1.5 sm:col-span-1">
                    <label
                        htmlFor={`${id}-number`}
                        className="text-eyebrow text-muted-foreground"
                    >
                        {t('vehicles.fields.number')}
                    </label>
                    <Input
                        id={`${id}-number`}
                        value={parts.number}
                        autoComplete="off"
                        autoCapitalize="characters"
                        placeholder="A45301"
                        aria-invalid={invalid}
                        className="font-bold tracking-widest tabular-nums placeholder:font-normal placeholder:tracking-normal placeholder:opacity-60"
                        onChange={(event) =>
                            update({
                                number: event.target.value
                                    .toUpperCase()
                                    .replace(/[^A-Z0-9]/g, '')
                                    .slice(0, 7),
                            })
                        }
                    />
                </div>
            </div>

            <p id={hintId} className="text-sm text-muted-foreground">
                {t('vehicles.plate_hint')}
            </p>

            {complete && (
                <p className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                    {t('vehicles.plate_preview')}
                    <PlateChip
                        plate={`${parts.region}-${parts.code.padStart(2, '0')}-${parts.number}`}
                    />
                </p>
            )}

            <InputError id={errorId} role="alert" message={error} />
        </fieldset>
    );
}
