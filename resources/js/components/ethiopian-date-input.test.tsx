import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'en',
            locales: { am: 'አማርኛ', en: 'English' },
            // Tikimt 1, 2018
            today: {
                gregorian: '2025-10-11',
                ethiopian: '01/02/2018',
                ethiopian_label: 'Tikimt 1, 2018',
            },
            translations: {
                permits: {
                    calendar: {
                        day: 'Day',
                        month: 'Month',
                        year: 'Year',
                        gregorian: 'Gregorian: :date',
                        choose: 'Choose a date',
                        today: 'Today',
                        clear: 'Clear',
                        previous_month: 'Previous month',
                        next_month: 'Next month',
                        weekdays: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                        ec: 'E.C.',
                        months: [
                            'Meskerem',
                            'Tikimt',
                            'Hidar',
                            'Tahsas',
                            'Tir',
                            'Yekatit',
                            'Megabit',
                            'Miazia',
                            'Ginbot',
                            'Sene',
                            'Hamle',
                            'Nehase',
                            'Pagume',
                        ],
                    },
                },
            },
        },
    }),
}));

function Harness({
    initial = '',
    error,
    yearsBefore,
    min,
    onValue,
}: {
    initial?: string;
    error?: string;
    yearsBefore?: number;
    min?: string;
    onValue?: (value: string) => void;
}) {
    const [value, setValue] = useState(initial);

    return (
        <EthiopianDateInput
            id="valid_from"
            label="Valid from"
            value={value}
            error={error}
            yearsBefore={yearsBefore}
            min={min}
            onChange={(next) => {
                setValue(next);
                onValue?.(next);
            }}
        />
    );
}

const field = () => screen.getByRole('button', { name: /Valid from/ });
const calendar = () => screen.getByRole('dialog', { name: 'Valid from' });
const days = () =>
    within(calendar())
        .getAllByRole('button')
        .filter((button) =>
            /E\.C\. \(/.test(button.getAttribute('aria-label') ?? ''),
        );

afterEach(cleanup);

describe('EthiopianDateInput', () => {
    it('is a labelled field that invites a choice', () => {
        render(<Harness />);

        expect(screen.getByLabelText('Valid from')).toBe(field());
        expect(field().textContent).toContain('Choose a date');
        expect(field().getAttribute('aria-haspopup')).toBe('dialog');
    });

    it('shows a Gregorian value as its Ethiopian date, with the Gregorian date below', () => {
        render(<Harness initial="2025-10-08" />);

        expect(field().textContent).toContain('Meskerem 28, 2018 E.C.');
        expect(screen.getByText('Gregorian: 2025-10-08')).toBeDefined();
    });

    it('opens on the current month with today marked', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await user.click(field());

        expect(within(calendar()).getByText('Tikimt')).toBeDefined();
        expect(days()).toHaveLength(30);
        expect(days()[0].getAttribute('aria-current')).toBe('date');
    });

    it('hands back the Gregorian date of the day chosen', async () => {
        const user = userEvent.setup();
        const onValue = vi.fn();
        render(<Harness onValue={onValue} />);

        await user.click(field());
        await user.click(
            within(calendar()).getByRole('button', {
                name: 'Tikimt 5, 2018 E.C. (2025-10-15)',
            }),
        );

        expect(onValue).toHaveBeenLastCalledWith('2025-10-15');
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(field().textContent).toContain('Tikimt 5, 2018 E.C.');
    });

    it('offers 5 days in Pagume of a common year', async () => {
        const user = userEvent.setup();
        render(<Harness yearsBefore={1} />);

        await user.click(field());
        await user.click(
            within(calendar()).getByRole('button', { name: 'Previous month' }),
        );
        await user.click(
            within(calendar()).getByRole('button', { name: 'Previous month' }),
        );

        expect(within(calendar()).getByText('Pagume')).toBeDefined();
        expect(days()).toHaveLength(5);
    });

    it('offers 6 days in Pagume of a leap year', async () => {
        const user = userEvent.setup();
        render(<Harness yearsBefore={3} />);

        await user.click(field());
        await user.selectOptions(
            within(calendar()).getByLabelText('Year'),
            '2016',
        );
        await user.click(
            within(calendar()).getByRole('button', { name: 'Previous month' }),
        );
        await user.click(
            within(calendar()).getByRole('button', { name: 'Previous month' }),
        );

        // Pagume 2015 (2016 opens on Tikimt, two months back is Pagume 2015).
        expect(within(calendar()).getByText('Pagume')).toBeDefined();
        expect(days()).toHaveLength(6);
    });

    it('chooses today, and clears', async () => {
        const user = userEvent.setup();
        const onValue = vi.fn();
        render(<Harness onValue={onValue} />);

        await user.click(field());
        await user.click(
            within(calendar()).getByRole('button', { name: 'Today' }),
        );
        expect(onValue).toHaveBeenLastCalledWith('2025-10-11');

        await user.click(field());
        await user.click(
            within(calendar()).getByRole('button', { name: 'Clear' }),
        );
        expect(onValue).toHaveBeenLastCalledWith('');
    });

    it('does not let a day before the earliest date be chosen', async () => {
        const user = userEvent.setup();
        render(<Harness min="2025-10-13" />);

        await user.click(field());

        expect((days()[0] as HTMLButtonElement).disabled).toBe(true);
        expect((days()[2] as HTMLButtonElement).disabled).toBe(false);
    });

    it('closes with Escape', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await user.click(field());
        await user.keyboard('{Escape}');

        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('announces the validation error with the field', () => {
        render(<Harness error="Choose a start date." />);

        expect(field().getAttribute('aria-invalid')).toBe('true');
        expect(field().getAttribute('aria-describedby')).toContain(
            'valid_from-error',
        );
        expect(screen.getByRole('alert').textContent).toBe(
            'Choose a start date.',
        );
    });
});
