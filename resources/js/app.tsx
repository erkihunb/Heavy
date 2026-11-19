import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import '../css/app.css';
import { initializeTheme } from '@/hooks/use-appearance';
import { appNameOf, appTitle } from '@/lib/app-title';

// The system's name as the server sends it (APP_NAME or System settings).
let appName = '';

createInertiaApp({
    title: (title) => appTitle(title, appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        appName = appNameOf(props.initialPage.props) ?? appName;
        // A name changed in System settings shows from the next page on.
        router.on('navigate', (event) => {
            appName = appNameOf(event.detail.page.props) ?? appName;
        });

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
