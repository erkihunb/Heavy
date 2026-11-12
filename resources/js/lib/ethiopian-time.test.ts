import { describe, expect, it } from 'vitest';
import { ethiopianTime } from '@/lib/ethiopian-time';

describe('ethiopianTime', () => {
    it('counts the hours from six in the morning and the evening', () => {
        expect(ethiopianTime('11:00')).toEqual({ hour: '5:00', period: 'day' });
        expect(ethiopianTime('14:00')).toEqual({ hour: '8:00', period: 'day' });
        expect(ethiopianTime('06:00')).toEqual({
            hour: '12:00',
            period: 'day',
        });
        expect(ethiopianTime('12:30')).toEqual({ hour: '6:30', period: 'day' });
        expect(ethiopianTime('18:00')).toEqual({
            hour: '12:00',
            period: 'evening',
        });
        expect(ethiopianTime('21:15')).toEqual({
            hour: '3:15',
            period: 'evening',
        });
        expect(ethiopianTime('00:00')).toEqual({
            hour: '6:00',
            period: 'night',
        });
        expect(ethiopianTime('05:59')).toEqual({
            hour: '11:59',
            period: 'night',
        });
    });

    it('gives nothing for a value that is not a time', () => {
        expect(ethiopianTime('')).toBeNull();
        expect(ethiopianTime('noon')).toBeNull();
    });
});
