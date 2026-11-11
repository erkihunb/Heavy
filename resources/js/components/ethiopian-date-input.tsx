import { usePage } from '@inertiajs/react';
import { CalendarMarkIcon } from '@solar-icons/react/linear/calendar-mark';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import InputError from '@/components/input-error';
import {
    MONTHS_IN_YEAR,
    daysInMonth,
    fromGregorianIso,
    toGregorianIso,
} from '@/lib/ethiopian-calendar';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/translations';

/** Day of the week (0 = Sunday) of a Gregorian ISO date. */
function weekday(iso: string): number {
    return new Date(`${iso}T00:00:00Z`).getUTCDay();
}

/**
 * A date chosen on an Ethiopian-calendar month grid (EUISIS date picker):
 * the field shows the chosen date in the Ethiopian calendar and opens a
 * calendar with the 13 months — Pagume with 5 or 6 days — the week days,
 * today marked, and Today / Clear. The value handed back is a Gregorian ISO
 * date, or "" when nothing is chosen; the Gregorian date is shown under the
 * field. This is the only place the frontend converts between the calendars.
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
    min,
    max,
}: {
    id: string;
    label: string;
    /** Gregorian ISO date, or "" when nothing is chosen. */
    value: string;
    onChange: (iso: string) => void;
    error?: string;
    hint?: string;
    /** How many years before / after the current Ethiopian year can be chosen. */
    yearsBefore?: number;
    yearsAfter?: number;
    /** Earliest and latest Gregorian ISO dates that can be chosen. */
    min?: string;
    max?: string;
}) {
    const { t, locale } = useTranslation();
    const { today } = usePage().props;
    const todayEth = fromGregorianIso(today.gregorian) ?? {
        year: 2018,
        month: 1,
        day: 1,
    };
    const selected = value ? fromGregorianIso(value) : null;

    const [open, setOpen] = useState(false);
    const [view, setView] = useState(() => ({
        year: selected?.year ?? todayEth.year,
        month: selected?.month ?? todayEth.month,
    }));
    const [position, setPosition] = useState({ top: 0, left: 0 });
    const triggerRef = useRef<HTMLButtonElement>(null);
    const popupRef = useRef<HTMLDivElement>(null);

    const firstYear = Math.min(
        todayEth.year - yearsBefore,
        selected?.year ?? Infinity,
    );
    const lastYear = Math.max(
        todayEth.year + yearsAfter,
        selected?.year ?? -Infinity,
    );

    const monthName = (month: number) =>
        t(`permits.calendar.months.${month - 1}` as TranslationKey);
    const weekdays = [0, 1, 2, 3, 4, 5, 6].map((index) =>
        t(`permits.calendar.weekdays.${index}` as TranslationKey),
    );
    const era = t('permits.calendar.ec');

    const show = (open: boolean) => {
        if (open) {
            setView({
                year: selected?.year ?? todayEth.year,
                month: selected?.month ?? todayEth.month,
            });
        }

        setOpen(open);
    };

    // Keep the calendar beside the field, inside the window.
    useLayoutEffect(() => {
        if (!open) {
            return;
        }

        const place = () => {
            if (!triggerRef.current || !popupRef.current) {
                return;
            }

            const anchor = triggerRef.current.getBoundingClientRect();
            const popup = popupRef.current.getBoundingClientRect();
            const gutter = 8;
            const below = window.innerHeight - anchor.bottom - gutter;
            const top =
                below < popup.height && anchor.top > below
                    ? anchor.top - popup.height - 4
                    : anchor.bottom + 4;

            setPosition({
                top: Math.max(
                    gutter,
                    Math.min(top, window.innerHeight - popup.height - gutter),
                ),
                left: Math.max(
                    gutter,
                    Math.min(
                        anchor.left,
                        window.innerWidth - popup.width - gutter,
                    ),
                ),
            });
        };

        place();
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);

        return () => {
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
        };
    }, [open, view]);

    // Close on a click outside; put focus on the chosen day (or today) when opened.
    useEffect(() => {
        if (!open) {
            return;
        }

        const outside = (event: MouseEvent) => {
            const target = event.target as Node;

            if (
                !triggerRef.current?.contains(target) &&
                !popupRef.current?.contains(target)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', outside);
        popupRef.current
            ?.querySelector<HTMLButtonElement>('[data-focus-day="true"]')
            ?.focus();

        return () => document.removeEventListener('mousedown', outside);
    }, [open]);

    const close = () => {
        setOpen(false);
        triggerRef.current?.focus();
    };

    const onPopupKey = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    };

    const step = (by: number) => {
        setView(({ year, month }) => {
            let nextMonth = month + by;
            let nextYear = year;

            if (nextMonth < 1) {
                nextMonth = MONTHS_IN_YEAR;
                nextYear--;
            } else if (nextMonth > MONTHS_IN_YEAR) {
                nextMonth = 1;
                nextYear++;
            }

            return nextYear < firstYear || nextYear > lastYear
                ? { year, month }
                : { year: nextYear, month: nextMonth };
        });
    };

    const isoOf = (day: number) =>
        toGregorianIso({ year: view.year, month: view.month, day });

    const disabledDay = (iso: string) =>
        (min !== undefined && iso < min) || (max !== undefined && iso > max);

    const choose = (iso: string) => {
        onChange(iso);
        close();
    };

    const dayCount = daysInMonth(view.year, view.month);
    const leading = weekday(isoOf(1));
    const todayIso = today.gregorian;
    const focusDay =
        selected && selected.year === view.year && selected.month === view.month
            ? selected.day
            : todayEth.year === view.year && todayEth.month === view.month
              ? todayEth.day
              : 1;

    const display = selected
        ? `${monthName(selected.month)} ${selected.day}, ${selected.year} ${era}`
        : '';

    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const gregorianId = value !== '' ? `${id}-gregorian` : undefined;
    const describedBy =
        [hintId, gregorianId, errorId].filter(Boolean).join(' ') || undefined;
    const years: number[] = [];

    for (let year = firstYear; year <= lastYear; year++) {
        years.push(year);
    }

    return (
        <div className="flex min-w-0 flex-col gap-2">
            <label htmlFor={id} className="text-sm leading-none font-medium">
                {label}
            </label>

            <button
                ref={triggerRef}
                id={id}
                type="button"
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-describedby={describedBy}
                aria-invalid={error ? true : undefined}
                onClick={() => show(!open)}
                className="flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-card px-3 text-start text-base transition-[color,box-shadow,border-color] outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25 aria-invalid:border-destructive md:text-sm pointer-coarse:h-11"
            >
                <span
                    className={cn(
                        'truncate',
                        !display && 'text-muted-foreground',
                    )}
                >
                    {display || t('permits.calendar.choose')}
                </span>
                <CalendarMarkIcon
                    aria-hidden="true"
                    className="size-4 shrink-0 text-muted-foreground"
                />
            </button>

            {hint && (
                <p id={hintId} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            {value !== '' && (
                <p
                    id={gregorianId}
                    className="text-xs text-muted-foreground tabular-nums"
                >
                    {t('permits.calendar.gregorian', { date: value })}
                </p>
            )}
            <InputError id={errorId} role="alert" message={error} />

            {open &&
                createPortal(
                    <div
                        ref={popupRef}
                        role="dialog"
                        aria-label={label}
                        lang={locale}
                        onKeyDown={onPopupKey}
                        className="fixed z-50 w-[18rem] max-w-[calc(100vw-1rem)] rounded-panel border border-border bg-popover text-popover-foreground shadow-lg"
                        style={position}
                    >
                        <div className="flex items-center justify-between gap-1 border-b border-border px-2 py-2">
                            <button
                                type="button"
                                onClick={() => step(-1)}
                                aria-label={t(
                                    'permits.calendar.previous_month',
                                )}
                                className="rounded-md p-1.5 hover:bg-muted"
                            >
                                <ChevronLeft
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </button>
                            <div className="flex items-center gap-1 text-sm font-medium">
                                <span>{monthName(view.month)}</span>
                                <select
                                    aria-label={t('permits.calendar.year')}
                                    value={view.year}
                                    onChange={(event) =>
                                        setView((current) => ({
                                            ...current,
                                            year: Number(event.target.value),
                                        }))
                                    }
                                    className="rounded-xs border border-transparent bg-transparent px-1 py-0.5 tabular-nums hover:border-border focus-visible:border-primary focus-visible:outline-none"
                                >
                                    {years.map((year) => (
                                        <option key={year} value={year}>
                                            {year}
                                        </option>
                                    ))}
                                </select>
                                <span className="text-muted-foreground">
                                    {era}
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={() => step(1)}
                                aria-label={t('permits.calendar.next_month')}
                                className="rounded-md p-1.5 hover:bg-muted"
                            >
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </button>
                        </div>

                        <div
                            className="grid grid-cols-7 px-2 pt-2"
                            aria-hidden="true"
                        >
                            {weekdays.map((name) => (
                                <div
                                    key={name}
                                    className="text-center text-xs font-medium text-muted-foreground"
                                >
                                    {name}
                                </div>
                            ))}
                        </div>

                        <div className="grid grid-cols-7 gap-0.5 p-2">
                            {Array.from({ length: leading }, (_, index) => (
                                <span
                                    key={`blank-${index}`}
                                    aria-hidden="true"
                                />
                            ))}
                            {Array.from({ length: dayCount }, (_, index) => {
                                const day = index + 1;
                                const iso = isoOf(day);
                                const isSelected = iso === value;
                                const isToday = iso === todayIso;
                                const disabled = disabledDay(iso);

                                return (
                                    <button
                                        key={day}
                                        type="button"
                                        disabled={disabled}
                                        aria-pressed={isSelected}
                                        aria-current={
                                            isToday ? 'date' : undefined
                                        }
                                        aria-label={`${monthName(view.month)} ${day}, ${view.year} ${era} (${iso})`}
                                        data-focus-day={day === focusDay}
                                        onClick={() => choose(iso)}
                                        className={cn(
                                            'rounded-md py-1.5 text-center text-sm tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                            isSelected &&
                                                'bg-primary font-semibold text-primary-foreground',
                                            !isSelected &&
                                                isToday &&
                                                'font-semibold text-primary ring-1 ring-primary ring-inset',
                                            !isSelected &&
                                                !disabled &&
                                                'hover:bg-muted',
                                            disabled &&
                                                'cursor-not-allowed text-muted-foreground/50',
                                        )}
                                    >
                                        {day}
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex justify-between border-t border-border px-3 py-2">
                            <button
                                type="button"
                                disabled={disabledDay(todayIso)}
                                onClick={() => choose(todayIso)}
                                className="text-xs font-medium text-primary hover:underline disabled:opacity-50"
                            >
                                {t('permits.calendar.today')}
                            </button>
                            {value && (
                                <button
                                    type="button"
                                    onClick={() => choose('')}
                                    className="text-xs text-muted-foreground hover:underline"
                                >
                                    {t('permits.calendar.clear')}
                                </button>
                            )}
                        </div>
                    </div>,
                    document.body,
                )}
        </div>
    );
}
