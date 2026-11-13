import { useEffect, useState } from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

/**
 * Whether this person asked their device for less motion, or an Admin turned
 * animations off for the whole system (System settings → Appearance, written
 * on <html data-animations>). Either one means: draw the finished state and
 * do not move it.
 *
 * Starts `true` on the server and before the first paint, so nothing can
 * animate in the gap before we know the answer.
 */
export function useReducedMotion(): boolean {
    const [reduced, setReduced] = useState(true);

    useEffect(() => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return;
        }

        const media = window.matchMedia(QUERY);
        const root = document.documentElement;
        const read = () =>
            setReduced(media.matches || root.dataset.animations === 'off');

        read();

        // Both can change while the page is open: the OS setting, and the
        // admin switch (the settings page writes it live).
        media.addEventListener('change', read);

        const observer = new MutationObserver(read);
        observer.observe(root, {
            attributes: true,
            attributeFilter: ['data-animations'],
        });

        return () => {
            media.removeEventListener('change', read);
            observer.disconnect();
        };
    }, []);

    return reduced;
}
