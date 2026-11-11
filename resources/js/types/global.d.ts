import type { Auth } from '@/types/auth';
import type { DualDate } from '@/types/calendar';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** One-off messages set by the previous request. */
            flash: {
                success: string | null;
                warning: string | null;
            };
            /** Current interface language, e.g. "am". */
            locale: string;
            /** Supported languages: code => name written in that language. */
            locales: Record<string, string>;
            /** Language lines for the namespaces in config/localization.php. */
            translations: Record<string, unknown>;
            /** Today in the display timezone, in both calendars. */
            today: DualDate;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
