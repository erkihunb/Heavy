import { useEffect, useState } from 'react';
import { DualDate } from '@/components/dual-date';
import InputError from '@/components/input-error';
import { useTranslation } from '@/lib/i18n';
import type { DualDate as DualDateValue, Money } from '@/types';

/**
 * A fee rate a permit can be registered at, as App\Http\Resources\FeeRateResource
 * sends it: the schedule line, its band of days, the days a permit at the
 * rate runs and its fee, and the day it would end once a start date is known.
 */
export type FeeRate = {
    id: number;
    days: number;
    min_days: number;
    max_days: number;
    fee: Money;
    valid_to: DualDateValue | null;
};

export type FeeRates =
    /** Not enough chosen yet to ask (no permit type or vehicle). */
    | { state: 'idle' }
    | { state: 'loading' }
    | { state: 'ready'; rates: FeeRate[]; datesFor: string };

type Loaded = { key: string; validFrom: string; rates: FeeRate[] };

/**
 * The rates offered today for the permit type and vehicle on the form,
 * asked of the server at `url` (null until both are chosen; `query` names
 * them and must not be empty) with the start date in `validFrom`, so each
 * rate comes with its end date. The list stays while only the start date
 * changes; `datesFor` says which start date the end dates belong to.
 */
export function useFeeRates(
    url: string | null,
    query: Record<string, string>,
    validFrom: string,
): FeeRates {
    const [loaded, setLoaded] = useState<Loaded | null>(null);
    const key = url === null ? null : `${url}?${new URLSearchParams(query)}`;

    useEffect(() => {
        if (key === null) {
            return;
        }

        const controller = new AbortController();
        // Wait for the person to stop changing the choices.
        const timer = window.setTimeout(() => {
            fetch(
                validFrom === ''
                    ? key
                    : `${key}&${new URLSearchParams({ valid_from: validFrom })}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            )
                .then((response) => (response.ok ? response.json() : null))
                .then((body: { rates: FeeRate[] } | null) => {
                    if (body !== null) {
                        setLoaded({ key, validFrom, rates: body.rates });
                    }
                })
                .catch(() => {
                    // Aborted or offline: the list is asked again on the next change.
                });
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [key, validFrom]);

    if (key === null) {
        return { state: 'idle' };
    }

    return loaded?.key === key
        ? { state: 'ready', rates: loaded.rates, datesFor: loaded.validFrom }
        : { state: 'loading' };
}

/**
 * The fee rate chosen from those offered, as radio cards: "30 days · ETB
 * 1,000.00" with the schedule's band of days. A rate the list no longer
 * offers (another permit type or vehicle was chosen) is let go.
 */
export function FeeRateField({
    rates,
    value,
    onChange,
    error,
}: {
    rates: FeeRates;
    /** The id of the chosen schedule line, or "" for none. */
    value: string;
    onChange: (id: string) => void;
    error?: string;
}) {
    const { t } = useTranslation();
    const offered = rates.state === 'ready' ? rates.rates : null;
    const stale =
        offered !== null &&
        value !== '' &&
        !offered.some((rate) => String(rate.id) === value);

    useEffect(() => {
        if (stale) {
            onChange('');
        }
    }, [stale, onChange]);

    const errorId = error ? 'fee_schedule_id-error' : undefined;

    return (
        <fieldset aria-describedby={errorId} className="space-y-2">
            <legend className="mb-2 text-sm font-medium">
                {t('permits.rates.label')}
            </legend>

            <div aria-live="polite">
                {rates.state === 'idle' && (
                    <p className="text-sm text-muted-foreground">
                        {t('permits.rates.choose_first')}
                    </p>
                )}
                {rates.state === 'loading' && (
                    <p className="text-sm text-muted-foreground">
                        {t('permits.rates.loading')}
                    </p>
                )}
                {offered !== null && offered.length === 0 && (
                    // No rate in the schedule: the request cannot be registered
                    // (the server refuses it), so say so before saving.
                    <p className="font-medium text-danger-strong">
                        {t('permits.errors.fee_not_set_for_registration')}
                    </p>
                )}
            </div>

            {offered !== null && offered.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2 @md:grid-cols-2">
                    {offered.map((rate) => (
                        <label
                            key={rate.id}
                            className="flex min-h-12 cursor-pointer items-center gap-3 rounded-card border-2 border-input px-4 py-2.5 transition-colors hover:border-primary/50 has-checked:border-primary has-checked:bg-primary/5 has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                        >
                            <input
                                type="radio"
                                name="fee_schedule_id"
                                value={rate.id}
                                checked={value === String(rate.id)}
                                onChange={() => onChange(String(rate.id))}
                                aria-invalid={error ? true : undefined}
                                className="size-5 shrink-0 accent-primary"
                            />
                            <span className="flex flex-col">
                                <span className="font-semibold tabular-nums">
                                    {t('permits.rates.option', {
                                        days: rate.days,
                                        fee: rate.fee.formatted,
                                    })}
                                </span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {t('fees.days_range', {
                                        min: rate.min_days,
                                        max: rate.max_days,
                                    })}
                                </span>
                            </span>
                        </label>
                    ))}
                </div>
            )}

            <InputError id={errorId} role="alert" message={error} />
        </fieldset>
    );
}

/**
 * The day the permit ends, worked out by the server from the start date and
 * the rate — shown, never typed — with the fee of the rate.
 */
export function PermitEndDate({
    label,
    rates,
    value,
    validFrom,
}: {
    label: string;
    rates: FeeRates;
    /** The id of the chosen schedule line, or "" for none. */
    value: string;
    validFrom: string;
}) {
    const { t } = useTranslation();
    const rate =
        rates.state === 'ready'
            ? (rates.rates.find((offered) => String(offered.id) === value) ??
              null)
            : null;
    const end =
        rate !== null &&
        validFrom !== '' &&
        rates.state === 'ready' &&
        rates.datesFor === validFrom
            ? rate.valid_to
            : null;

    return (
        <div
            aria-live="polite"
            className="space-y-3 rounded-card border border-border bg-muted px-4 py-3"
        >
            <div className="space-y-1">
                <p className="text-eyebrow text-muted-foreground">{label}</p>
                {end !== null ? (
                    <>
                        <DualDate date={end} className="text-lg" />
                        <p className="text-xs text-muted-foreground">
                            {t('permits.rates.end_hint')}
                        </p>
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('permits.rates.end_pending')}
                    </p>
                )}
            </div>
            {rate !== null && (
                <div className="space-y-1">
                    <p className="text-eyebrow text-muted-foreground">
                        {t('applicants.intake.fee')}
                    </p>
                    <p className="flex flex-wrap items-baseline gap-x-2">
                        <span className="text-xl font-extrabold tabular-nums">
                            {rate.fee.formatted}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {t('payments.fee_days', { days: rate.days })}
                        </span>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('applicants.intake.fee_after_approval')}
                    </p>
                </div>
            )}
        </div>
    );
}
