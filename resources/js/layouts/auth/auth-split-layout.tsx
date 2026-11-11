import { Link } from '@inertiajs/react';
import { CalendarDays, ScanQrCode, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { useTranslation } from '@/lib/i18n';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights: {
    key: 'calendar' | 'qr' | 'security';
    icon: LucideIcon;
}[] = [
    { key: 'calendar', icon: CalendarDays },
    { key: 'qr', icon: ScanQrCode },
    { key: 'security', icon: ShieldCheck },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslation();

    return (
        <div className="grid min-h-svh bg-background text-foreground lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            {/* Brand panel (large screens) */}
            <aside className="relative hidden flex-col justify-between gap-12 surface-asphalt p-10 lg:flex xl:p-14">
                <Link
                    href={home()}
                    className="flex w-fit items-center gap-3 rounded-lg"
                >
                    <BrandMark className="size-11 shrink-0" />
                    <span className="leading-tight font-extrabold">
                        {t('common.app_name')}
                    </span>
                </Link>

                <div className="space-y-9">
                    <p className="text-4xl text-display xl:text-5xl">
                        {t('common.welcome.title')}
                    </p>
                    <div
                        className="lane-dash max-w-xs text-highlight"
                        aria-hidden="true"
                    />
                    <ul className="space-y-5">
                        {highlights.map(({ key, icon: Icon }) => (
                            <li key={key} className="flex items-center gap-4">
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl border-2 border-highlight text-highlight">
                                    <Icon
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                </span>
                                <span className="font-semibold">
                                    {t(`landing.features.${key}.title`)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="text-sm text-asphalt-muted">
                    {t('common.authority')}
                </p>
            </aside>

            {/* Form side */}
            <div className="flex min-w-0 flex-col">
                <div className="brand-bar lg:hidden" aria-hidden="true" />

                <header className="flex items-center justify-between gap-3 px-4 py-4 sm:px-8">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 rounded-lg lg:invisible"
                    >
                        <BrandMark className="size-10 shrink-0" />
                        <span className="sr-only">{t('common.app_name')}</span>
                    </Link>
                    <LanguageSwitcher />
                </header>

                <main className="flex flex-1 items-center justify-center px-4 pt-4 pb-14 sm:px-8">
                    <div className="w-full max-w-md animate-rise space-y-8">
                        <div className="space-y-2">
                            <h1 className="text-3xl text-display sm:text-4xl">
                                {title}
                            </h1>
                            <p className="text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
