import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Toaster } from 'sonner';
import { toast } from '@/shell/lib/toast';

interface FlashProps {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
    message?: string | null;
    type?: string | null;
}

type DocumentTheme = 'light' | 'dark';

/** The flash data already shown as toasts (see AppToaster). */
let shownFlash: FlashProps | undefined;

function readDocumentTheme(): DocumentTheme {
    return typeof document !== 'undefined' &&
        document.documentElement.classList.contains('dark')
        ? 'dark'
        : 'light';
}

/**
 * Sonner's own `theme="system"` only follows the operating system, but the
 * app's theme switch (lib/theme.ts) toggles the `dark` class on <html>, so a
 * toast has to follow that class or it renders light on a dark page.
 */
function useDocumentTheme(): DocumentTheme {
    const [theme, setTheme] = useState<DocumentTheme>(readDocumentTheme);

    useEffect(() => {
        const update = () => setTheme(readDocumentTheme());
        update();

        const observer = new MutationObserver(update);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => observer.disconnect();
    }, []);

    return theme;
}

/**
 * The one toaster, mounted once per layout.
 *
 * - Renders Sonner's <Toaster> (top-center, rich colours, close button).
 * - Listens for Inertia flash messages and dispatches them via toast, unless
 *   `flashToasts` is off for a page that shows its flash inline instead.
 * - On a phone (Sonner switches below 600px) toasts span the screen width and
 *   keep clear of a notch; they can be swiped away.
 */
export default function AppToaster({
    flashToasts = true,
}: {
    flashToasts?: boolean;
}) {
    const page = usePage();
    // The message the last action left (HandleInertiaRequests sends `success`
    // and `warning`; the other keys are read when present). It is Inertia
    // flash data, not a page prop: a prop is saved with the page in the
    // browser's history, and going back to the page showed the message again.
    const flash = (flashToasts ? page.flash : undefined) as
        FlashProps | undefined;
    const theme = useDocumentTheme();

    // Keyed on the flash object itself, NOT the URL: every visit brings its
    // own object, so the same text shows again after a later action, even one
    // that redirects back to the same page. `shownFlash` lives outside the
    // component because each page mounts its own layout: a toaster mounted
    // again on the same page must not repeat the message.
    useEffect(() => {
        if (!flash || flash === shownFlash) return;
        shownFlash = flash;

        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
        if (flash.warning) toast.warning(flash.warning);
        if (flash.info) toast.info(flash.info);

        const hadKeys = Boolean(
            flash.success || flash.error || flash.warning || flash.info,
        );
        if (!hadKeys && flash.message) {
            const type = flash.type;
            if (type === 'success') toast.success(flash.message);
            else if (type === 'error') toast.error(flash.message);
            else if (type === 'warning') toast.warning(flash.message);
            else toast.info(flash.message);
        }
    }, [flash]);

    return (
        <Toaster
            theme={theme}
            position="top-center"
            richColors
            closeButton
            duration={5000}
            mobileOffset={{
                top: 'max(12px, env(safe-area-inset-top))',
                left: 12,
                right: 12,
            }}
            toastOptions={{
                classNames: {
                    toast: 'rounded-card shadow-lg text-sm leading-snug',
                },
            }}
        />
    );
}
