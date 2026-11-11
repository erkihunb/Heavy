import AppLogoIcon from '@/components/app-logo-icon';
import { useTranslation } from '@/lib/i18n';

// The icon is the starter kit's placeholder mark. The Authority's official
// logo is an open question (docs/DECISIONS.md) and must not be invented.
export default function AppLogo() {
    const { t } = useTranslation();

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {t('common.app_name')}
                </span>
            </div>
        </>
    );
}
