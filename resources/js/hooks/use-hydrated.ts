import { useSyncExternalStore } from 'react';

const subscribe = (): (() => void) => () => {};

/**
 * False while a page is drawn on the server and while the browser takes over
 * that HTML; true from then on, and from the very first render when the page
 * was not drawn on the server (INERTIA_SSR_ENABLED off).
 *
 * The server cannot see what only the browser knows (localStorage, the kind
 * of keyboard). A component that shows such a thing draws the server's guess
 * while this is false, so both draw the same HTML, and the real value as soon
 * as it is true.
 */
export function useHydrated(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => true,
        () => false,
    );
}
