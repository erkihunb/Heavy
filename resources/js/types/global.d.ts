import type { Auth } from '@/types/auth';
import type { DualDate } from '@/types/calendar';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        /**
         * One-off messages left by the previous request. Flash data, not a
         * page prop: it is shown once and never kept in the browser's history.
         */
        flashDataType: {
            success?: string;
            warning?: string;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** Current interface language, e.g. "am". */
            locale: string;
            /** Supported languages: code => name written in that language. */
            locales: Record<string, string>;
            /** Language lines for the namespaces in config/localization.php. */
            translations: Record<string, unknown>;
            /** Today in the display timezone, in both calendars. */
            today: DualDate;
            sidebarOpen: boolean;
            appearance: {
                allowUserMode: boolean;
                showBreadcrumbs: boolean;
                showLanguageSwitcher: boolean;
            };
            /**
             * Settings the admin shell reads, flat and keyed as in the source kit
             * ('appearance.primary_color', …); missing on a page whose middleware never ran.
             */
            settings?: Record<string, unknown>;
            /** System settings → General; missing on a page whose middleware never ran. */
            branding?: {
                logo: string | null;
                favicon: string | null;
                supportEmail: string | null;
                supportPhone: string | null;
                loginMessage: string | null;
            };
            [key: string]: unknown;
        };
    }
}
