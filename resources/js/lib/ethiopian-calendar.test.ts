import { describe, expect, it } from 'vitest';
import {
    daysInMonth,
    fromGregorianIso,
    isLeapYear,
    toGregorianIso,
} from '@/lib/ethiopian-calendar';

// The same known pairs as tests/Unit/Support/EthiopianCalendarTest.php.
const pairs: [string, number, number, number][] = [
    ['2025-10-08', 2018, 1, 28],
    ['2025-09-11', 2018, 1, 1],
    ['2025-09-10', 2017, 13, 5],
    ['2023-09-11', 2015, 13, 6],
    ['2023-09-12', 2016, 1, 1],
    ['2024-02-29', 2016, 6, 21],
    ['2026-01-06', 2018, 4, 28],
    ['2000-01-01', 1992, 4, 22],
];

describe('ethiopian calendar', () => {
    it.each(pairs)('converts %s both ways', (iso, year, month, day) => {
        expect(fromGregorianIso(iso)).toEqual({ year, month, day });
        expect(toGregorianIso({ year, month, day })).toBe(iso);
    });

    it('knows leap years and the length of Pagume', () => {
        expect(isLeapYear(2015)).toBe(true);
        expect(isLeapYear(2018)).toBe(false);
        expect(daysInMonth(2015, 13)).toBe(6);
        expect(daysInMonth(2018, 13)).toBe(5);
        expect(daysInMonth(2018, 12)).toBe(30);
    });

    it('rejects text that is not an ISO date', () => {
        expect(fromGregorianIso('')).toBeNull();
        expect(fromGregorianIso('28/01/2018')).toBeNull();
    });
});
