import { BrandMark } from '@/components/brand-mark';
import { useTranslation } from '@/lib/i18n';

export default function AppLogo() {
    const { t } = useTranslation();

    return (
        <>
            <span className="flex size-9 shrink-0 items-center justify-center">
                <BrandMark className="size-9" />
            </span>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-extrabold">
                    {t('common.app_name')}
                </span>
            </div>
        </>
    );
}
