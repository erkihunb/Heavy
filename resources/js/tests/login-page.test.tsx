import { cleanup, render, screen, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The sign-in page. The point of these is the promise the page makes: the
 * form works straight away, whatever the illustration beside it is doing,
 * and a refused sign-in shakes the card exactly once.
 */

const { formState } = vi.hoisted(() => ({
    formState: {
        processing: false,
        errors: {} as Record<string, string>,
    },
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Form: ({
        children,
    }: {
        children: (state: typeof formState) => ReactNode;
    }) => <form>{children(formState)}</form>,
    usePage: () => ({
        props: { branding: {}, appearance: { showLanguageSwitcher: false } },
    }),
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

vi.mock('@/lib/i18n', () => ({
    useTranslation: () => ({ t: (key: string) => key, locale: 'en' }),
    useLocale: () => ({ t: (key: string) => key, locale: 'en' }),
}));

vi.mock('@/routes/login', () => ({ store: { form: () => ({}) } }));
vi.mock('@/routes/password', () => ({ request: () => '/forgot-password' }));
vi.mock('@/routes', () => ({
    home: () => '/',
    login: () => '/login',
    logout: () => '/logout',
}));

/* The illustration is stubbed: these tests are about the form, not the scene. */
vi.mock('@/components/auth/login-hero/LoginHero', () => ({
    LoginHero: () => <div data-testid="hero" />,
    default: () => <div data-testid="hero" />,
}));
vi.mock('@/components/auth/login-hero/StaticHero', () => ({
    StaticHero: () => <div data-testid="static-hero" />,
}));

import Login from '@/pages/auth/login';

function setReducedMotion(reduce: boolean) {
    vi.stubGlobal(
        'matchMedia',
        vi.fn((query: string) => ({
            matches: query.includes('prefers-reduced-motion') ? reduce : false,
            media: query,
            onchange: null,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            addListener: vi.fn(),
            removeListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })),
    );
}

beforeEach(() => {
    formState.processing = false;
    formState.errors = {};
    setReducedMotion(false);

    // jsdom has no ResizeObserver; the checkbox's Radix primitive wants one.
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe = vi.fn();
            unobserve = vi.fn();
            disconnect = vi.fn();
        },
    );
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('the sign-in page', () => {
    it('renders a usable form, whatever the illustration is doing', () => {
        render(<Login canResetPassword />);

        // The form is what people came for: it is there on the first render,
        // and does not wait on the scene beside it.
        expect(screen.getByLabelText('auth_ui.email')).toBeTruthy();
        expect(screen.getByLabelText('auth_ui.password')).toBeTruthy();
        expect(
            screen.getByRole('button', { name: /auth_ui.login.submit/ }),
        ).toBeTruthy();
    });

    it('ties each field to its error, and shakes the card once', async () => {
        formState.errors = { email: 'These credentials do not match.' };

        const { container } = render(<Login canResetPassword />);

        const email = screen.getByLabelText('auth_ui.email');
        expect(email.getAttribute('aria-invalid')).toBe('true');
        expect(email.getAttribute('aria-describedby')).toBe('email-error');

        const card = container.querySelector('.grid.gap-5');
        await waitFor(() => {
            expect(card?.classList.contains('hero-shake')).toBe(true);
        });

        // One pass only: the class comes off when the animation ends, and
        // does not come back without a new error.
        card?.dispatchEvent(new Event('animationend'));
        expect(card?.classList.contains('hero-shake')).toBe(false);
    });

    it('does not shake when the device asks for less motion', async () => {
        setReducedMotion(true);
        formState.errors = { password: 'wrong' };

        const { container } = render(<Login canResetPassword />);
        const card = container.querySelector('.grid.gap-5');

        await waitFor(() => {
            expect(screen.getByLabelText('auth_ui.password')).toBeTruthy();
        });
        expect(card?.classList.contains('hero-shake')).toBe(false);
    });

    it('disables the submit button while the form is being sent', () => {
        formState.processing = true;

        render(<Login canResetPassword />);

        const button = screen.getByRole('button', {
            name: /auth_ui.login.submit/,
        });
        expect(button.hasAttribute('disabled')).toBe(true);
    });
});
