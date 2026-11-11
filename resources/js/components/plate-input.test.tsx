import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PlateInput } from '@/components/plate-input';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'en',
            locales: { am: 'አማርኛ', en: 'English' },
            translations: {
                vehicles: {
                    fields: {
                        plate: 'Plate number',
                        region: 'Region',
                        code: 'Code',
                        number: 'Number',
                    },
                    plate_hint: 'Choose the region, then enter the code.',
                    plate_preview: 'Plate as it will be saved',
                },
            },
        },
    }),
}));

const regions = [
    { value: 'ኢት', latin: 'ET' },
    { value: 'አአ', latin: 'AA' },
];

function Harness({
    initial = '',
    error,
    onValue,
}: {
    initial?: string;
    error?: string;
    onValue?: (value: string) => void;
}) {
    const [value, setValue] = useState(initial);

    return (
        <PlateInput
            id="plate"
            value={value}
            regions={regions}
            error={error}
            onChange={(next) => {
                setValue(next);
                onValue?.(next);
            }}
        />
    );
}

afterEach(cleanup);

describe('PlateInput', () => {
    it('is a labelled group of three labelled controls', () => {
        render(<Harness />);

        const group = screen.getByRole('group', { name: 'Plate number' });

        expect(within(group).getByLabelText('Region')).toBeTruthy();
        expect(within(group).getByLabelText('Code')).toBeTruthy();
        expect(within(group).getByLabelText('Number')).toBeTruthy();
    });

    it('offers only the given regions, each with its Latin spelling', () => {
        render(<Harness />);

        const options = within(screen.getByLabelText('Region')).getAllByRole(
            'option',
        );

        expect(options.map((option) => option.textContent)).toEqual([
            '—',
            'ኢት · ET',
            'አአ · AA',
        ]);
    });

    it('shows an existing plate split into its parts', () => {
        render(<Harness initial="አአ-03-A45301" />);

        expect(
            (screen.getByLabelText('Region') as HTMLSelectElement).value,
        ).toBe('አአ');
        expect((screen.getByLabelText('Code') as HTMLInputElement).value).toBe(
            '03',
        );
        expect(
            (screen.getByLabelText('Number') as HTMLInputElement).value,
        ).toBe('A45301');
    });

    it('reports the plate as region-code-number while it is being entered', async () => {
        const onValue = vi.fn();
        render(<Harness onValue={onValue} />);

        await userEvent.selectOptions(screen.getByLabelText('Region'), 'ኢት');
        await userEvent.type(screen.getByLabelText('Code'), '03');
        await userEvent.type(screen.getByLabelText('Number'), '40134');

        expect(onValue).toHaveBeenLastCalledWith('ኢት-03-40134');
    });

    it('accepts only digits in the code and at most two of them', async () => {
        render(<Harness initial="ኢት--" />);

        await userEvent.type(screen.getByLabelText('Code'), 'a0b3c9');

        expect((screen.getByLabelText('Code') as HTMLInputElement).value).toBe(
            '03',
        );
    });

    it('upper-cases the number and drops separators and spaces', async () => {
        const onValue = vi.fn();
        render(<Harness initial="አአ-03-" onValue={onValue} />);

        await userEvent.type(screen.getByLabelText('Number'), 'a 45-301');

        expect(
            (screen.getByLabelText('Number') as HTMLInputElement).value,
        ).toBe('A45301');
        expect(onValue).toHaveBeenLastCalledWith('አአ-03-A45301');
    });

    it('keeps the whole plate when it is pasted with spaces and dashes', async () => {
        const onValue = vi.fn();
        render(<Harness initial="ኢት--" onValue={onValue} />);

        await userEvent.click(screen.getByLabelText('Code'));
        await userEvent.paste(' 03 ');
        await userEvent.click(screen.getByLabelText('Number'));
        await userEvent.paste('a4 01-34');

        expect(onValue).toHaveBeenLastCalledWith('ኢት-03-A40134');
    });

    it('does not let the browser truncate input before it is cleaned', () => {
        render(<Harness />);

        expect(screen.getByLabelText('Code').hasAttribute('maxlength')).toBe(
            false,
        );
        expect(screen.getByLabelText('Number').hasAttribute('maxlength')).toBe(
            false,
        );
    });

    it('reports an empty value once every part is cleared', async () => {
        const onValue = vi.fn();
        render(<Harness initial="ኢት-03-" onValue={onValue} />);

        await userEvent.clear(screen.getByLabelText('Code'));
        await userEvent.selectOptions(screen.getByLabelText('Region'), '');

        expect(onValue).toHaveBeenLastCalledWith('');
    });

    it('previews the plate as it will be saved, with the code zero-padded', async () => {
        render(<Harness initial="አአ-3-A45301" />);

        expect(screen.getByText('አአ-03-A45301')).toBeTruthy();
    });

    it('shows no preview until the plate is complete', () => {
        render(<Harness initial="አአ-03-" />);

        expect(screen.queryByText('Plate as it will be saved')).toBeNull();
    });

    it('announces a validation error and ties it to the group', () => {
        render(<Harness error="This vehicle is already in your register." />);

        const alert = screen.getByRole('alert');
        const group = screen.getByRole('group', { name: 'Plate number' });

        expect(alert.textContent).toBe(
            'This vehicle is already in your register.',
        );
        expect(group.getAttribute('aria-describedby')).toContain(alert.id);
        expect(
            screen.getByLabelText('Number').getAttribute('aria-invalid'),
        ).toBe('true');
    });

    it('is reached in reading order with the keyboard and can be typed into', async () => {
        const onValue = vi.fn();
        render(<Harness initial="አአ--" onValue={onValue} />);

        await userEvent.tab();
        expect(document.activeElement).toBe(screen.getByLabelText('Region'));

        await userEvent.tab();
        expect(document.activeElement).toBe(screen.getByLabelText('Code'));
        await userEvent.keyboard('03');

        await userEvent.tab();
        expect(document.activeElement).toBe(screen.getByLabelText('Number'));
        await userEvent.keyboard('a45301');

        expect(onValue).toHaveBeenLastCalledWith('አአ-03-A45301');
    });
});
