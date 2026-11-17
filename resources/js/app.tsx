import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import '../css/app.css';
import { initializeTheme } from '@/hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const app = (
            <StrictMode>
                <App {...props} />
            </StrictMode>
        );

        // Already drawn by the server (INERTIA_SSR_ENABLED, resources/js/ssr.tsx):
        // take over that HTML instead of throwing it away and drawing it again.
        if (el.hasChildNodes()) {
            hydrateRoot(el, app);

            return;
        }

        createRoot(el).render(app);
    },
    progress: {
        color: '#1F7A4D',
    },
});

// This will set light / dark mode on load...
initializeTheme();
