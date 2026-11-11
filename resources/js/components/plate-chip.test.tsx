import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { PlateChip } from '@/components/plate-chip';

afterEach(cleanup);

describe('PlateChip', () => {
    it('renders the plate exactly as given and opts out of machine translation', () => {
        render(<PlateChip plate="አአ-03-A45301" />);

        const chip = screen.getByText('አአ-03-A45301');

        expect(chip.getAttribute('translate')).toBe('no');
    });

    it('never wraps a plate across lines', () => {
        render(<PlateChip plate="ኢት-03-40134" />);

        expect(screen.getByText('ኢት-03-40134').className).toContain(
            'whitespace-nowrap',
        );
    });
});
