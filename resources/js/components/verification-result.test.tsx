import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { VerificationResult } from '@/components/verification-result';
import { SCAN_RESULTS } from '@/types/enums';

const results = Object.fromEntries(
    SCAN_RESULTS.map((result) => [
        result,
        { title: `title:${result}`, body: `body:${result}` },
    ]),
);

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'en',
            locales: { am: 'አማርኛ', en: 'English' },
            translations: {
                verification: {
                    results,
                    checked_at: 'Checked on :date at :time',
                },
            },
        },
    }),
}));

const checkedAt = {
    gregorian: '2025-10-08',
    ethiopian: '28/01/2018',
    ethiopian_label: 'Meskerem 28, 2018',
};

afterEach(cleanup);

describe('VerificationResult', () => {
    it.each(SCAN_RESULTS)('states the "%s" result as a heading', (result) => {
        render(
            <VerificationResult
                result={result}
                checkedAt={checkedAt}
                checkedTime="09:15"
            />,
        );

        expect(
            screen.getByRole('heading', { name: `title:${result}` }),
        ).toBeDefined();
        expect(screen.getByText(`body:${result}`)).toBeDefined();
    });

    it('says when the check was made, in the Ethiopian calendar', () => {
        render(
            <VerificationResult
                result="valid"
                checkedAt={checkedAt}
                checkedTime="09:15"
            />,
        );

        expect(
            screen.getByText('Checked on Meskerem 28, 2018 at 09:15'),
        ).toBeDefined();
    });

    it('does not rely on colour alone', () => {
        const { container } = render(
            <VerificationResult
                result="revoked"
                checkedAt={checkedAt}
                checkedTime="09:15"
            />,
        );

        expect(
            container
                .querySelector('[data-result="revoked"] svg')
                ?.getAttribute('aria-hidden'),
        ).toBe('true');
    });
});
