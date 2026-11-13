import { cleanup, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LoginHero from '@/components/auth/login-hero/LoginHero';
import { StaticHero } from '@/components/auth/login-hero/StaticHero';

/**
 * The illustration beside the sign-in form. What matters here is not how it
 * looks but what it promises: it never speaks to assistive technology, and
 * it holds completely still for anyone who asked for less motion.
 */

/** Pretend the device does, or does not, ask for less motion. */
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
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            observe = vi.fn();
            disconnect = vi.fn();
            unobserve = vi.fn();
            takeRecords = vi.fn(() => []);
            root = null;
            rootMargin = '';
            thresholds = [];
        },
    );
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    document.documentElement.removeAttribute('data-animations');
});

describe('the sign-in illustration', () => {
    it('is hidden from assistive technology', () => {
        setReducedMotion(false);
        const { container } = render(<LoginHero />);

        // The whole illustration is decorative: the wrapper hides the lot,
        // and the drawing inside it carries no meaning of its own.
        const scene = container.firstElementChild;
        expect(scene?.getAttribute('aria-hidden')).toBe('true');

        const svg = container.querySelector('svg');
        expect(svg).not.toBeNull();
        expect(svg?.getAttribute('role')).toBe('presentation');
        expect(svg?.closest('[aria-hidden="true"]')).not.toBeNull();
    });

    it('holds still when the device asks for less motion', async () => {
        setReducedMotion(true);
        const { container } = render(<LoginHero />);

        // Nothing that loops: no traced roads, no travelling vehicles.
        await waitFor(() => {
            expect(container.querySelector('.hero-trace')).toBeNull();
        });
        expect(container.querySelector('.hero-haul')).toBeNull();
        expect(container.querySelector('.hero-aurora')).toBeNull();

        // The vehicles are still drawn: they are parked on their roads.
        expect(
            container.querySelectorAll('.hero-haul-static').length,
        ).toBeGreaterThan(0);
    });

    it('holds still when an Admin turned animations off', async () => {
        setReducedMotion(false);
        document.documentElement.dataset.animations = 'off';

        const { container } = render(<LoginHero />);

        await waitFor(() => {
            expect(container.querySelector('.hero-trace')).toBeNull();
        });
        expect(container.querySelector('.hero-haul')).toBeNull();
    });

    it('runs its loops when motion is allowed', async () => {
        setReducedMotion(false);
        const { container } = render(<LoginHero />);

        await waitFor(() => {
            expect(container.querySelector('.hero-trace')).not.toBeNull();
        });
        expect(container.querySelectorAll('.hero-haul').length).toBe(3);
    });

    it('draws the still version with no looping classes at all', () => {
        const { container } = render(<StaticHero />);

        for (const loop of ['.hero-trace', '.hero-haul', '.hero-aurora']) {
            expect(container.querySelector(loop)).toBeNull();
        }

        expect(screen.queryByRole('img')).toBeNull();
    });
});
