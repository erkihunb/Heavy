/**
 * Ethiopian (Amete Mihret) ↔ Gregorian conversion for the date picker — the
 * only place the frontend converts dates (everything else is sent by the
 * server in both calendars). Mirrors App\Support\EthiopianCalendar: integer
 * arithmetic over the Julian Day Number, no timezones involved.
 */

export type EthiopianDate = { year: number; month: number; day: number };

/** Julian Day Number offset of the Amete Mihret era. */
const ERA_OFFSET = 1723856;

export const MONTHS_IN_YEAR = 13;

export function isLeapYear(year: number): boolean {
    return year % 4 === 3;
}

export function daysInMonth(year: number, month: number): number {
    if (month <= 12) {
        return 30;
    }

    return isLeapYear(year) ? 6 : 5;
}

function gregorianToJdn(year: number, month: number, day: number): number {
    const a = Math.floor((14 - month) / 12);
    const y = year + 4800 - a;
    const m = month + 12 * a - 3;

    return (
        day +
        Math.floor((153 * m + 2) / 5) +
        365 * y +
        Math.floor(y / 4) -
        Math.floor(y / 100) +
        Math.floor(y / 400) -
        32045
    );
}

function jdnToGregorian(jdn: number): [number, number, number] {
    const a = jdn + 32044;
    const b = Math.floor((4 * a + 3) / 146097);
    const c = a - Math.floor((146097 * b) / 4);
    const d = Math.floor((4 * c + 3) / 1461);
    const e = c - Math.floor((1461 * d) / 4);
    const m = Math.floor((5 * e + 2) / 153);

    return [
        100 * b + d - 4800 + Math.floor(m / 10),
        m + 3 - 12 * Math.floor(m / 10),
        e - Math.floor((153 * m + 2) / 5) + 1,
    ];
}

const pad = (value: number, length = 2) => String(value).padStart(length, '0');

/** Ethiopian date → Gregorian ISO date ("2025-10-08"). */
export function toGregorianIso({ year, month, day }: EthiopianDate): string {
    const jdn =
        ERA_OFFSET +
        365 +
        365 * (year - 1) +
        Math.floor(year / 4) +
        30 * month +
        day -
        31;
    const [gYear, gMonth, gDay] = jdnToGregorian(jdn);

    return `${pad(gYear, 4)}-${pad(gMonth)}-${pad(gDay)}`;
}

/** Gregorian ISO date → Ethiopian date, or null when the text is not a date. */
export function fromGregorianIso(iso: string): EthiopianDate | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);

    if (!match) {
        return null;
    }

    const sinceEra =
        gregorianToJdn(Number(match[1]), Number(match[2]), Number(match[3])) -
        ERA_OFFSET;
    const cycleDay = sinceEra % 1461;
    const dayOfYear = (cycleDay % 365) + 365 * Math.floor(cycleDay / 1460);

    return {
        year:
            4 * Math.floor(sinceEra / 1461) +
            Math.floor(cycleDay / 365) -
            Math.floor(cycleDay / 1460),
        month: Math.floor(dayOfYear / 30) + 1,
        day: (dayOfYear % 30) + 1,
    };
}
