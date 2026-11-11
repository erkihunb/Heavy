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
    onValue,
}: {
    initial?: string;
    error?: string;
    yearsBefore?: number;
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
            onChange={(next) => {
                setValue(next);
                onValue?.(next);
            }}
        />
    );
}

const day = () => screen.getByLabelText('Day') as HTMLSelectElement;
const month = () => screen.getByLabelText('Month') as HTMLSelectElement;
const year = () => screen.getByLabelText('Year') as HTMLSelectElement;

afterEach(cleanup);

describe('EthiopianDateInput', () => {
    it('groups day, month and year under the field label', () => {
        render(<Harness />);

        const group = screen.getByRole('group', { name: 'Valid from' });

        expect(within(group).getAllByRole('combobox')).toHaveLength(3);
        expect(within(month()).getAllByRole('option')).toHaveLength(14);
        expect(
            within(month()).getByRole('option', { name: 'Pagume' }),
        ).toBeDefined();
    });

    it('shows a Gregorian value as its Ethiopian date', () => {
        render(<Harness initial="2025-10-08" />);

        expect(day().value).toBe('28');
        expect(month().value).toBe('1');
        expect(year().value).toBe('2018');
        expect(screen.getByText('Gregorian: 2025-10-08')).toBeDefined();
    });

    it('submits a Gregorian ISO date once all three parts are chosen', async () => {
        const user = userEvent.setup();
        const onValue = vi.fn();
        render(<Harness onValue={onValue} />);

        await user.selectOptions(day(), '28');
        await user.selectOptions(month(), '1');

        expect(onValue).toHaveBeenLastCalledWith('');

        await user.selectOptions(year(), '2018');

        expect(onValue).toHaveBeenLastCalledWith('2025-10-08');
    });

    it('offers the current Ethiopian year and the next by default', () => {
        render(<Harness />);

        const years = within(year())
            .getAllByRole('option')
            .map((option) => option.textContent);

        expect(years).toEqual(['—', '2018', '2019']);
    });

    it('keeps the year of an existing value selectable', () => {
        render(<Harness initial="2023-09-11" />);

        expect(year().value).toBe('2015');
    });

    it('offers 5 days in Pagume of a common year', async () => {
        const user = userEvent.setup();
        const onValue = vi.fn();
        render(<Harness onValue={onValue} />);

        await user.selectOptions(year(), '2018');
        await user.selectOptions(month(), '13');

        // 5 days and the empty option
        expect(within(day()).getAllByRole('option')).toHaveLength(6);

        await user.selectOptions(day(), '5');

        expect(onValue).toHaveBeenLastCalledWith('2026-09-10');
    });

    it('offers 6 days in Pagume of a leap year', async () => {
        const user = userEvent.setup();
        const onValue = vi.fn();
        render(<Harness onValue={onValue} yearsBefore={3} />);

        await user.selectOptions(year(), '2015');
        await user.selectOptions(month(), '13');

        expect(within(day()).getAllByRole('option')).toHaveLength(7);

        await user.selectOptions(day(), '6');

        expect(onValue).toHaveBeenLastCalledWith('2023-09-11');
    });

    it('clears a day that does not exist in the newly chosen month', async () => {
        const user = userEvent.setup();
        const onValue = vi.fn();
        render(<Harness initial="2025-10-10" onValue={onValue} />);

        expect(day().value).toBe('30');

        await user.selectOptions(month(), '13');

        expect(day().value).toBe('');
        expect(onValue).toHaveBeenLastCalledWith('');
    });

    it('announces the validation error with the group', () => {
        render(<Harness error="The first day cannot be in the past." />);

        const group = screen.getByRole('group', { name: 'Valid from' });
        const alert = screen.getByRole('alert');

        expect(alert.textContent).toBe('The first day cannot be in the past.');
        expect(group.getAttribute('aria-describedby')).toContain(alert.id);
        expect(day().getAttribute('aria-invalid')).toBe('true');
    });
});
