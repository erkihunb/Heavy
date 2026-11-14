import { cleanup, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';

const guards = vi.hoisted(() => ({
    before: [] as ((event: {
        detail: { visit: { method: string } };
        preventDefault: () => void;
    }) => void)[],
}));

vi.mock('@inertiajs/react', () => ({
    router: {
        on: (_name: string, callback: (typeof guards.before)[number]) => {
            guards.before.push(callback);

            return () => {
                guards.before = guards.before.filter(
                    (guard) => guard !== callback,
                );
            };
        },
    },
}));

/** Whether the browser would ask "Reload site?" now. */
function browserAsks(): boolean {
    const event = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(event);

    return event.defaultPrevented;
}

describe('useUnsavedChangesGuard', () => {
    afterEach(() => {
        cleanup();
        guards.before = [];
        vi.restoreAllMocks();
    });

    it('asks before the tab is closed or reloaded while there are unsaved changes', () => {
        renderHook(() => useUnsavedChangesGuard(true, 'Leave?'));

        expect(browserAsks()).toBe(true);
    });

    it('does not ask when nothing has changed', () => {
        renderHook(() => useUnsavedChangesGuard(false, 'Leave?'));

        expect(browserAsks()).toBe(false);
        expect(guards.before).toHaveLength(0);
    });

    it('does not ask when the page reloads itself right after saving', () => {
        // Saving reloads in the same tick, before React has seen that the
        // form is clean again: the guard is still attached at that moment.
        const { result } = renderHook(() =>
            useUnsavedChangesGuard(true, 'Leave?'),
        );

        result.current.allowLeaving();

        expect(browserAsks()).toBe(false);
    });

    it('asks before another page is opened, and stays when the answer is no', () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        renderHook(() => useUnsavedChangesGuard(true, 'Leave?'));
        const preventDefault = vi.fn();

        guards.before[0]({
            detail: { visit: { method: 'get' } },
            preventDefault,
        });
        guards.before[0]({
            detail: { visit: { method: 'put' } },
            preventDefault,
        });

        expect(window.confirm).toHaveBeenCalledExactlyOnceWith('Leave?');
        expect(preventDefault).toHaveBeenCalledTimes(1);
    });
});
