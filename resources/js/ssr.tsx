import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import { appNameOf, appTitle } from '@/lib/app-title';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        // The name the server sent with this page (APP_NAME or System settings).
        title: (title) => appTitle(title, appNameOf(page.props) ?? ''),
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob('./pages/**/*.tsx'),
            ),
        setup: ({ App, props }) => {
            return <App {...props} />;
        },
    }),
);
