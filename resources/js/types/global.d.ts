import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** Current interface language, e.g. "am". */
            locale: string;
            /** Supported languages: code => name written in that language. */
            locales: Record<string, string>;
            /** Language lines for the namespaces in config/localization.php. */
            translations: Record<string, unknown>;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
