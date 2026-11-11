import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { StatusBadge } from '@/components/status-badge';
import { PERMIT_STATUSES } from '@/types/enums';

const labels = Object.fromEntries(
    PERMIT_STATUSES.map((status) => [status, `label:${status}`]),
);

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'en',
            locales: { am: 'አማርኛ', en: 'English' },
            translations: { permits: { status: labels } },
        },
    }),
}));

afterEach(cleanup);

describe('StatusBadge', () => {
    it.each(PERMIT_STATUSES)('names the "%s" status', (status) => {
        render(<StatusBadge status={status} />);

        const badge = screen.getByText(`label:${status}`);

        expect(badge.getAttribute('data-status')).toBe(status);
        // The icon is decorative: the status is carried by the text.
        expect(badge.querySelector('svg')?.getAttribute('aria-hidden')).toBe(
            'true',
        );
    });

    it.each([
        ['issued', 'valid'],
        ['active', 'valid'],
        ['revoked', 'stopped'],
        ['rejected', 'stopped'],
        ['expired', 'ended'],
        ['withdrawn', 'ended'],
        ['changes_requested', 'attention'],
        ['awaiting_payment', 'attention'],
        ['under_review', 'progress'],
        ['draft', 'neutral'],
    ] as const)('shows "%s" in the %s tone', (status, tone) => {
        render(<StatusBadge status={status} />);

        expect(
            screen.getByText(`label:${status}`).getAttribute('data-tone'),
        ).toBe(tone);
    });
});
