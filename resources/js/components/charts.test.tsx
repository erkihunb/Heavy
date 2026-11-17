import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import {
    DonutChart,
    Meter,
    niceScale,
    SegmentedBar,
    smoothLine,
} from '@/components/charts';

afterEach(cleanup);

describe('niceScale', () => {
    it('rounds the top of the axis up to 1, 2 or 5 times a power of ten', () => {
        expect(niceScale(337)).toEqual({ top: 400, step: 100 });
        expect(niceScale(2)).toEqual({ top: 2, step: 1 });
        expect(niceScale(1000)).toEqual({ top: 1000, step: 500 });
        expect(niceScale(7)).toEqual({ top: 8, step: 2 });
    });

    it('never steps by less than one, and draws an empty axis to four', () => {
        expect(niceScale(0)).toEqual({ top: 4, step: 1 });
        expect(niceScale(1)).toEqual({ top: 1, step: 1 });
    });
});

describe('SegmentedBar', () => {
    it('names every part with its number and share', () => {
        render(
            <SegmentedBar
                shareLabel="Share"
                parts={[
                    { key: 'a', label: 'Approved', value: 3, color: 'bg-x' },
                    { key: 'b', label: 'Rejected', value: 1, color: 'bg-y' },
                    { key: 'c', label: 'Returned', value: 0, color: 'bg-z' },
                ]}
            />,
        );

        expect(
            screen.getByRole('img', {
                name: 'Approved: 3 (75%), Rejected: 1 (25%)',
            }),
        ).toBeTruthy();
        expect(screen.getByText('Returned')).toBeTruthy();
    });
});

describe('DonutChart', () => {
    it('names every part with its share and shows the total in the centre', () => {
        render(
            <DonutChart
                shareLabel="Share"
                totalLabel="Permits"
                parts={[
                    { key: 'a', label: 'Active', value: 6, color: 'bg-x' },
                    { key: 'b', label: 'Expired', value: 2, color: 'bg-y' },
                    { key: 'c', label: 'Revoked', value: 0, color: 'bg-z' },
                ]}
            />,
        );

        expect(
            screen.getByRole('img', {
                name: 'Active: 6 (75%), Expired: 2 (25%)',
            }),
        ).toBeTruthy();
        expect(screen.getByText('8')).toBeTruthy();
        expect(screen.getByText('Revoked')).toBeTruthy();
    });
});

describe('smoothLine', () => {
    it('keeps a flat run flat instead of bulging between equal points', () => {
        const path = smoothLine([
            { x: 0, y: 50 },
            { x: 50, y: 50 },
            { x: 100, y: 50 },
        ]);

        expect(path).toBe(
            'M0,50 C16.67,50 33.33,50 50,50 C66.67,50 83.33,50 100,50',
        );
    });
});

describe('Meter', () => {
    it('reports its progress and keeps it between 0 and 100', () => {
        render(<Meter label="Approval rate" percent={140} />);

        const bar = screen.getByRole('progressbar', { name: 'Approval rate' });

        expect(bar.getAttribute('aria-valuenow')).toBe('100');
        expect(screen.getByText('100%')).toBeTruthy();
    });
});
