import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

/**
 * How the scripts are grouped into files.
 *
 * Left to itself the build makes a separate file of every module that two
 * pages share: an icon, a button, a label. A first visit then asked for 20 to
 * 40 files, most of them under a kilobyte, before it could draw anything; on a
 * slow phone line every one of them costs a round trip (measured: the last
 * arrived a second after the largest). So the small shared modules are put
 * into four files instead:
 *
 *   vendor  the packages app.tsx itself needs (React, Inertia and theirs). It
 *           only changes when a package does, so a deploy leaves it cached.
 *   shared  what both the sign-in pages and the signed-in pages use.
 *   guest   what only the sign-in pages share.
 *   shell   the signed-in layout and what the signed-in pages share.
 *
 * A module that few pages use and that is not small stays where the build
 * puts it (with its page, or in a file of its own), so no page is made to
 * download a large thing it does not show.
 */
const FEW_PAGES = 6;
const MANY_PAGES = 15;
const SMALL = 3_000;
const MEDIUM = 30_000;

type ModuleInfoLike = {
    code: string | null;
    importedIds: readonly string[];
    dynamicImporters: readonly string[];
} | null;

type ChunkContext = {
    getModuleInfo: (id: string) => ModuleInfoLike;
    getModuleIds: () => IterableIterator<string>;
};

function chunkPlan({
    getModuleInfo,
    getModuleIds,
}: ChunkContext): Map<string, string> {
    const plan = new Map<string, string>();
    const ids = [...getModuleIds()];
    const isScript = (id: string): boolean => !/\.css($|\?)/.test(id);

    /** A module and everything it imports statically. */
    const closure = (root: string): Set<string> => {
        const seen = new Set<string>([root]);

        for (const id of seen) {
            for (const imported of getModuleInfo(id)?.importedIds ?? []) {
                seen.add(imported);
            }
        }

        return seen;
    };

    const entry = ids.find((id) => id.endsWith('/resources/js/app.tsx'));
    const layout = ids.find((id) =>
        id.endsWith('/resources/js/shell/Layouts/AuthenticatedLayout.tsx'),
    );

    if (entry === undefined || layout === undefined) {
        return plan;
    }

    const entryModules = closure(entry);
    const layoutModules = closure(layout);
    const pages = ids.filter(
        (id) =>
            /\/resources\/js\/pages\/.+\.tsx$/.test(id) &&
            (getModuleInfo(id)?.dynamicImporters.length ?? 0) > 0,
    );

    // How many sign-in pages and how many signed-in pages reach each module.
    const guest = new Map<string, number>();
    const shell = new Map<string, number>();

    for (const page of pages) {
        const reached = closure(page);
        const count = reached.has(layout) ? shell : guest;

        for (const id of reached) {
            count.set(id, (count.get(id) ?? 0) + 1);
        }
    }

    for (const id of ids) {
        if (id === entry || pages.includes(id) || !isScript(id)) {
            continue;
        }

        const inEntry = entryModules.has(id);
        const guests = guest.get(id) ?? 0;
        const shells = shell.get(id) ?? 0;
        const size = getModuleInfo(id)?.code?.length ?? 0;

        // Packages and the build's own helpers: everything that is not our source.
        if (inEntry && !id.includes('/resources/js/')) {
            plan.set(id, 'vendor');
        } else if (inEntry && guests + shells > 0) {
            plan.set(id, 'shared');
        } else if (guests > 0 && shells > 0) {
            if (guests + shells >= FEW_PAGES || size < SMALL) {
                plan.set(id, 'shared');
            }
        } else if (guests >= 2) {
            plan.set(id, 'guest');
        } else if (shells > 0) {
            if (
                layoutModules.has(id) ||
                (shells >= MANY_PAGES && size < MEDIUM) ||
                (shells >= 2 && size < SMALL)
            ) {
                plan.set(id, 'shell');
            }
        }
    }

    return plan;
}

let plannedChunks: Map<string, string> | undefined;

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                // Only the modules named by the plan; nothing is pulled in after them.
                onlyExplicitManualChunks: true,
                manualChunks(id, context) {
                    plannedChunks ??= chunkPlan(context);

                    return plannedChunks.get(id);
                },
            },
        },
    },
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
    // The server-rendering bundle (bootstrap/ssr, `npm run build:ssr`) carries
    // its packages with it, so the "ssr" image needs Node and that folder only.
    ssr: {
        noExternal: true,
    },
    // An IPv4 address for the dev server: a Content-Security-Policy cannot name
    // an IPv6 one ([::1]), which blocked the stylesheet of the Blade pages.
    server: {
        host: '127.0.0.1',
    },
    esbuild: {
        jsx: 'automatic',
    },
});
