import { Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { ThemeToggle } from '@/components/theme-toggle';
import { useTranslation } from '@/lib/i18n';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in, registration and password pages (EUISIS guest layout): a single
 * centered card on the muted page colour, with the brand band on its top
 * edge, the system name above it and the Authority's name below.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-svh flex-col bg-muted text-foreground">
            <header className="flex items-center justify-end gap-1.5 px-4 py-3 sm:px-6">
                <ThemeToggle className="flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-card hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none" />
                <LanguageSwitcher />
            </header>

            <main className="flex flex-1 flex-col items-center justify-center px-4 pb-12">
                <Link
                    href={home()}
                    className="mb-8 flex items-center gap-3 rounded-md focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                >
                    <BrandMark className="size-10 shrink-0" />
                    <span className="max-w-xs text-base leading-tight font-bold">
                        {t('common.app_name')}
                    </span>
                </Link>

                <div className="w-full max-w-md animate-rise overflow-hidden rounded-panel border border-border bg-card shadow-lg">
                    <div className="brand-bar" aria-hidden="true" />
                    <div className="space-y-7 px-6 py-8 sm:px-9">
                        <div className="space-y-1.5">
                            <h1 className="text-page-title">{title}</h1>
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>

                <p className="mt-8 max-w-md text-center text-xs text-muted-foreground">
                    {t('common.authority')}
                </p>
            </main>
        </div>
    );
}
