import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PaymentStatusBadge } from '@/components/payment-status-badge';
import { PAYMENT_STATUSES } from '@/types/enums';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'en',
            locales: { am: 'አማርኛ', en: 'English' },
            translations: {
                payments: {
                    status: {
                        submitted: 'Waiting for verification',
                        verified: 'Verified',
                        rejected: 'Rejected',
                    },
                },
            },
        },
    }),
}));

afterEach(cleanup);

describe('PaymentStatusBadge', () => {
    it.each(PAYMENT_STATUSES)('names the "%s" status in words', (status) => {
        render(<PaymentStatusBadge status={status} />);

        const badge = document.querySelector(`[data-status="${status}"]`);

        expect(badge?.textContent).not.toBe('');
        // The icon only repeats the colour; the words carry the meaning.
        expect(badge?.querySelector('svg')?.getAttribute('aria-hidden')).toBe(
            'true',
        );
    });

    it('says a rejected payment is rejected', () => {
        render(<PaymentStatusBadge status="rejected" />);

        expect(screen.getByText('Rejected')).toBeDefined();
    });
});
