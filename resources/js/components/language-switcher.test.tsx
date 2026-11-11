import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { LanguageSwitcher } from '@/components/language-switcher';

const { put, page } = vi.hoisted(() => ({
    put: vi.fn(),
    page: {
        props: {
            locale: 'am',
            locales: { am: 'አማርኛ', en: 'English' },
            translations: { common: { language: 'ቋንቋ' } },
        },
    },
}));

vi.mock('@inertiajs/react', () => ({
    router: { put },
    usePage: () => page,
}));

vi.mock('@/routes/locale', () => ({
    update: { url: () => '/locale' },
}));

afterEach(() => {
    cleanup();
    put.mockClear();
});

describe('LanguageSwitcher', () => {
    it('offers every supported language, labelled in its own script', () => {
        render(<LanguageSwitcher />);

        expect(screen.getByRole('group', { name: 'ቋንቋ' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'አማርኛ' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'English' })).toBeTruthy();
    });

    it('marks the current language as pressed', () => {
        render(<LanguageSwitcher />);

        expect(
            screen
                .getByRole('button', { name: 'አማርኛ' })
                .getAttribute('aria-pressed'),
        ).toBe('true');
        expect(
            screen
                .getByRole('button', { name: 'English' })
                .getAttribute('aria-pressed'),
        ).toBe('false');
    });

    it('asks the server to switch when another language is chosen', async () => {
        render(<LanguageSwitcher />);

        await userEvent.click(screen.getByRole('button', { name: 'English' }));

        expect(put).toHaveBeenCalledTimes(1);
        expect(put).toHaveBeenCalledWith(
            '/locale',
            { locale: 'en' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('does nothing when the current language is chosen again', async () => {
        render(<LanguageSwitcher />);

        await userEvent.click(screen.getByRole('button', { name: 'አማርኛ' }));

        expect(put).not.toHaveBeenCalled();
    });

    it('is operable from the keyboard', async () => {
        render(<LanguageSwitcher />);

        await userEvent.tab();
        await userEvent.tab();
        await userEvent.keyboard('{Enter}');

        expect(put).toHaveBeenCalledWith(
            '/locale',
            { locale: 'en' },
            expect.anything(),
        );
    });
});
