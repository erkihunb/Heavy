import { Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import type { VehicleType } from '@/types';
import { PERMIT_TYPES } from '@/types/enums';
import type { PermitType } from '@/types/enums';

export type FeeScheduleFormData = {
    permit_type: PermitType | '';
    /** "" = every vehicle type without a fee of its own. */
    vehicle_type_id: string;
    min_days: string;
    max_days: string;
    /** Birr, e.g. "1500" or "1500.50". */
    amount: string;
    /** Gregorian ISO dates; the date picker converts from the Ethiopian calendar. */
    effective_from: string;
    effective_to: string;
};

/**
 * One line of the fee schedule: which permits it prices, for how many days,
 * the amount, and when it is in force.
 */
export function FeeScheduleForm({
    form,
    onSubmit,
    vehicleTypes,
    cancelHref,
}: {
    form: InertiaFormProps<FeeScheduleFormData>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    vehicleTypes: VehicleType[];
    cancelHref: string;
}) {
    const { t } = useTranslation();

    return (
        <form
            onSubmit={onSubmit}
            noValidate
            className="max-w-3xl space-y-7 rounded-card border border-border bg-card p-5 sm:p-6"
        >
            <fieldset className="space-y-2">
                <legend className="mb-2 text-sm leading-none font-medium">
                    {t('fees.fields.permit_type')}
                </legend>
                <div className="grid gap-3 sm:grid-cols-2">
                    {PERMIT_TYPES.map((type) => (
                        <label
                            key={type}
                            className="flex min-h-12 cursor-pointer items-center gap-3 rounded-card border-2 border-input px-4 py-3 font-bold has-checked:border-primary has-checked:bg-accent has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                        >
                            <input
                                type="radio"
                                name="permit_type"
                                value={type}
                                checked={form.data.permit_type === type}
                                onChange={() =>
                                    form.setData('permit_type', type)
                                }
                                className="size-5 accent-primary"
                            />
                            {t(`permits.types.${type}`)}
                        </label>
                    ))}
                </div>
                <InputError role="alert" message={form.errors.permit_type} />
            </fieldset>

            <FormField
                id="vehicle_type_id"
                label={t('fees.fields.vehicle_type')}
                hint={t('fees.all_types_hint')}
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
                        <option value="">{t('fees.all_vehicle_types')}</option>
                        {vehicleTypes.map((type) => (
                            <option key={type.id} value={type.id}>
                                {type.name}
                            </option>
                        ))}
                    </NativeSelect>
                )}
            </FormField>

            <div className="space-y-2">
                <div className="grid gap-6 sm:grid-cols-2">
                    <FormField
                        id="min_days"
                        label={t('fees.fields.min_days')}
                        error={form.errors.min_days}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                value={form.data.min_days}
                                inputMode="numeric"
                                className="tabular-nums"
                                onChange={(event) =>
                                    form.setData('min_days', event.target.value)
                                }
                            />
                        )}
                    </FormField>
                    <FormField
                        id="max_days"
                        label={t('fees.fields.max_days')}
                        error={form.errors.max_days}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                value={form.data.max_days}
                                inputMode="numeric"
                                className="tabular-nums"
                                onChange={(event) =>
                                    form.setData('max_days', event.target.value)
                                }
                            />
                        )}
                    </FormField>
                </div>
                <p className="text-sm text-muted-foreground">
                    {t('fees.form.days_hint')}
                </p>
            </div>

            <FormField
                id="amount"
                label={t('fees.fields.amount')}
                error={form.errors.amount}
            >
                {(control) => (
                    <Input
                        {...control}
                        value={form.data.amount}
                        inputMode="decimal"
                        className="tabular-nums"
                        onChange={(event) =>
                            form.setData('amount', event.target.value)
                        }
                    />
                )}
            </FormField>

            <div className="grid gap-6 md:grid-cols-2">
                <EthiopianDateInput
                    id="effective_from"
                    label={t('fees.fields.effective_from')}
                    value={form.data.effective_from}
                    error={form.errors.effective_from}
                    yearsBefore={2}
                    yearsAfter={2}
                    onChange={(iso) => form.setData('effective_from', iso)}
                />
                <EthiopianDateInput
                    id="effective_to"
                    label={t('fees.fields.effective_to')}
                    value={form.data.effective_to}
                    error={form.errors.effective_to}
                    hint={t('fees.form.effective_to_hint')}
                    yearsBefore={2}
                    yearsAfter={3}
                    onChange={(iso) => form.setData('effective_to', iso)}
                />
            </div>

            <div className="flex flex-col gap-3 sm:flex-row">
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {t('fees.form.save')}
                </Button>
                <Button asChild variant="outline" size="lg">
                    <Link href={cancelHref}>{t('fees.form.cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
