import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

export default defineConfig({
    resolve: {
        alias: {
            // The shell's UI kit (packages/app-ui), ported from the source system.
            '@app/ui': fileURLToPath(
                new URL('./packages/app-ui/src/index.ts', import.meta.url),
            ),
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
                'resources/js/verify-scanner.ts',
            ],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    // An IPv4 address for the dev server: a Content-Security-Policy cannot name
    // an IPv6 one ([::1]), which blocked the stylesheet of the Blade pages.
    server: {
        host: '127.0.0.1',
    },
    esbuild: {
        jsx: 'automatic',
    },
});
