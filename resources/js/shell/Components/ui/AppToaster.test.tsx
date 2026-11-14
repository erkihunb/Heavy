import { cleanup, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AppToaster from '@/shell/Components/ui/AppToaster';

const page = vi.hoisted(() => ({
    current: { props: {}, flash: {} } as {
        props: Record<string, unknown>;
        flash: Record<string, unknown>;
    },
}));
const toast = vi.hoisted(() => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({ usePage: () => page.current }));
vi.mock('@/shell/lib/toast', () => ({ toast }));
vi.mock('sonner', () => ({ Toaster: () => null }));

describe('AppToaster', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });
    afterEach(cleanup);

    it('shows the message of the visit that brought it', () => {
        page.current = { props: {}, flash: { success: 'Approved.' } };
        render(<AppToaster />);

        expect(toast.success).toHaveBeenCalledExactlyOnceWith('Approved.');
    });

    it('does not show it again when the layout mounts again on the same page', () => {
        page.current = { props: {}, flash: { success: 'Approved.' } };
        render(<AppToaster />);
        cleanup();
        render(<AppToaster />);

        expect(toast.success).toHaveBeenCalledTimes(1);
    });

    it('shows nothing for a page restored from history, which has no flash data', () => {
        // What the browser's Back button used to bring: the message as a prop.
        page.current = {
            props: { flash: { success: 'Approved.', warning: null } },
            flash: {},
        };
        render(<AppToaster />);

        expect(toast.success).not.toHaveBeenCalled();
    });

    it('shows the same text again when a later action brings it', () => {
        page.current = { props: {}, flash: { success: 'Saved.' } };
        render(<AppToaster />);
        cleanup();
        page.current = { props: {}, flash: { success: 'Saved.' } };
        render(<AppToaster />);

        expect(toast.success).toHaveBeenCalledTimes(2);
    });
});
