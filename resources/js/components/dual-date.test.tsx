import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { DualDate } from '@/components/dual-date';

const date = {
    gregorian: '2025-10-08',
    ethiopian: '28/01/2018',
    ethiopian_label: 'መስከረም 28, 2018',
};

afterEach(cleanup);

describe('DualDate', () => {
    it('shows the Ethiopian date first and the Gregorian date as secondary text', () => {
        const { container } = render(<DualDate date={date} />);

        expect(container.textContent).toBe('መስከረም 28, 20182025-10-08');
    });

    it('marks up the date for machines with the Gregorian ISO value', () => {
        render(<DualDate date={date} />);

        const time = screen.getByText('መስከረም 28, 2018');

        expect(time.tagName).toBe('TIME');
        expect(time.getAttribute('datetime')).toBe('2025-10-08');
    });
});
