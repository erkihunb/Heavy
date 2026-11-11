/**
 * A date in both calendars, exactly as produced by
 * App\Support\EthiopianCalendar::present(). The frontend never converts dates
 * itself (the date picker is the only exception).
 */
export type DualDate = {
    /** ISO date, e.g. "2025-10-08". */
    gregorian: string;
    /** Numeric Ethiopian date, e.g. "28/01/2018". */
    ethiopian: string;
    /** Labelled Ethiopian date in the current language, e.g. "መስከረም 28, 2018". */
    ethiopian_label: string;
};
