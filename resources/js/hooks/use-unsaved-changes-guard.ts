import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';

/**
 * Leaving a form with unsaved changes asks first: another page of the app
 * (a question in `message`), or closing or reloading the tab (the browser's
 * own "Reload site?" question).
 *
 * `allowLeaving()` is for a page that reloads itself right after a successful
 * save. That reload runs before React has seen that the form is clean again,
 * so the guard is still attached and the browser would ask about changes
 * that were just saved.
 */
export function useUnsavedChangesGuard(
    dirty: boolean,
    message: string,
): { allowLeaving: () => void } {
    const leaving = useRef(false);

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!leaving.current) {
                event.preventDefault();
            }
        };
        const removeRouterGuard = router.on('before', (event) => {
            if (leaving.current || event.detail.visit.method !== 'get') {
                return;
            }

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        window.addEventListener('beforeunload', onBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            removeRouterGuard();
        };
    }, [dirty, message]);

    const allowLeaving = useCallback(() => {
        leaving.current = true;
    }, []);

    return { allowLeaving };
}
