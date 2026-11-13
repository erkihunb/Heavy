import { useEffect, useState } from 'react';

/**
 * Whether the tab is currently on screen. A looping animation reads this and
 * stops while the tab is in the background, so a forgotten sign-in page in
 * another tab does not keep a device busy.
 */
export function usePageVisibility(): boolean {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const read = () => setVisible(!document.hidden);

        read();
        document.addEventListener('visibilitychange', read);

        return () => document.removeEventListener('visibilitychange', read);
    }, []);

    return visible;
}

/**
 * Whether an element is within (or near) the viewport. The hero uses it to
 * stop its loops once it is scrolled out of sight, which happens on a narrow
 * screen where the panel is a band above the form.
 */
export function useInView<T extends Element>(
    ref: React.RefObject<T | null>,
): boolean {
    const [inView, setInView] = useState(true);

    useEffect(() => {
        const element = ref.current;

        if (!element || typeof IntersectionObserver === 'undefined') {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => setInView(entry.isIntersecting),
            { rootMargin: '64px' },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [ref]);

    return inView;
}
