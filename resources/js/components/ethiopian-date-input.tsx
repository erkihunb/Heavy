import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/ui/native-select';
import {
    MONTHS_IN_YEAR,
    daysInMonth,
    fromGregorianIso,
    toGregorianIso,
} from '@/lib/ethiopian-calendar';
import { useTranslation } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translations';

type Parts = { day: string; month: string; year: string };

const EMPTY: Parts = { day: '', month: '', year: '' };

function partsOf(iso: string): Parts {
    const date = fromGregorianIso(iso);

    return date === null
        ? EMPTY
        : {
              day: String(date.day),
              month: String(date.month),
              year: String(date.year),
          };
}

function isComplete(parts: Parts): boolean {
    return parts.day !== '' && parts.month !== '' && parts.year !== '';
}

/**
 * A date chosen in the Ethiopian calendar (day / month / year, including
 * Pagume) and handed back as a Gregorian ISO date — or "" while incomplete.
 * This is the only place the frontend converts between the calendars.
 */
export function EthiopianDateInput({
    id,
    label,
    value,
    onChange,
    error,
    hint,
    yearsBefore = 0,
    yearsAfter = 1,
}: {
    id: string;
    label: string;
    /** Gregorian ISO date, or "" when nothing is chosen. */
    value: string;
    onChange: (iso: string) => void;
    error?: string;
    hint?: string;
    /** How many years before / after the current Ethiopian year are offered. */
    yearsBefore?: number;
    yearsAfter?: number;
}) {
    const { t } = useTranslation();
    const { today } = usePage().props;
    const [parts, setParts] = useState<Parts>(() => partsOf(value));
    const [lastValue, setLastValue] = useState(value);

    // The value was changed from outside (form reset, server round-trip).
    if (value !== lastValue) {
        setLastValue(value);
        setParts(partsOf(value));
    }

    const currentYear = fromGregorianIso(today.gregorian)?.year ?? 0;
    const yearOf = (candidate: Parts): number =>
        candidate.year === '' ? currentYear : Number(candidate.year);

    const years = new Set<number>();

    for (
        let year = currentYear - yearsBefore;
        year <= currentYear + yearsAfter;
        year++
    ) {
        years.add(year);
    }

    if (parts.year !== '') {
        years.add(Number(parts.year));
    }

    const dayCount =
        parts.month === ''
            ? 30
            : daysInMonth(yearOf(parts), Number(parts.month));

    const change = (part: keyof Parts, next: string) => {
        const updated = { ...parts, [part]: next };

        // Pagume has 5 or 6 days: a day that no longer exists is cleared.
        if (
            updated.day !== '' &&
            updated.month !== '' &&
            Number(updated.day) >
                daysInMonth(yearOf(updated), Number(updated.month))
        ) {
            updated.day = '';
        }

        const iso = isComplete(updated)
            ? toGregorianIso({
                  year: Number(updated.year),
                  month: Number(updated.month),
                  day: Number(updated.day),
              })
            : '';

        setParts(updated);
        setLastValue(iso);
        onChange(iso);
    };

    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const gregorianId = value !== '' ? `${id}-gregorian` : undefined;
    const describedBy =
        [hintId, gregorianId, errorId].filter(Boolean).join(' ') || undefined;

    const select = (
        part: keyof Parts,
        partLabel: string,
        options: { value: number; label: string }[],
    ) => (
        <div className="grid min-w-0 gap-1">
            <label
                htmlFor={`${id}-${part}`}
                className="text-eyebrow text-muted-foreground"
            >
                {partLabel}
            </label>
            <NativeSelect
                id={`${id}-${part}`}
                value={parts[part]}
                aria-invalid={error ? true : undefined}
                onChange={(event) => change(part, event.target.value)}
            >
                <option value="">—</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </NativeSelect>
        </div>
    );

    return (
        <fieldset
            id={id}
            aria-describedby={describedBy}
            className="flex min-w-0 flex-col gap-2"
        >
            <legend className="mb-2 text-sm leading-none font-medium">
                {label}
            </legend>

            <div className="grid grid-cols-[minmax(0,4.5rem)_minmax(0,1fr)_minmax(0,6rem)] gap-2">
                {select(
                    'day',
                    t('permits.calendar.day'),
                    Array.from({ length: dayCount }, (_, index) => ({
                        value: index + 1,
                        label: String(index + 1),
                    })),
                )}
                {select(
                    'month',
                    t('permits.calendar.month'),
                    Array.from({ length: MONTHS_IN_YEAR }, (_, index) => ({
                        value: index + 1,
                        label: t(
                            `permits.calendar.months.${index}` as TranslationKey,
                        ),
                    })),
                )}
                {select(
                    'year',
                    t('permits.calendar.year'),
                    [...years]
                        .sort((a, b) => a - b)
                        .map((year) => ({ value: year, label: String(year) })),
                )}
            </div>

            {hint && (
                <p id={hintId} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            {value !== '' && (
                <p
                    id={gregorianId}
                    className="text-sm text-muted-foreground tabular-nums"
                >
                    {t('permits.calendar.gregorian', { date: value })}
                </p>
            )}
            <InputError id={errorId} role="alert" message={error} />
        </fieldset>
    );
}
