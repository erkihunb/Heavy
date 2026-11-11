import { Head, Link, usePage } from '@inertiajs/react';
import { LanguageSwitcher } from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { dashboard, login, register } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('common.welcome.title')} />

            <div className="flex min-h-svh flex-col bg-background text-foreground">
                <header className="flex items-center justify-between gap-4 border-b border-border px-4 py-3 sm:px-6">
                    <p className="text-sm font-medium text-muted-foreground">
                        {t('common.authority')}
                    </p>
                    <LanguageSwitcher />
                </header>

                <main className="mx-auto flex w-full max-w-2xl flex-1 flex-col justify-center gap-6 px-4 py-12 sm:px-6">
                    <h1 className="text-3xl leading-tight font-semibold sm:text-4xl">
                        {t('common.welcome.title')}
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        {t('common.welcome.intro')}
                    </p>

                    <nav
                        aria-label={t('common.app_name')}
                        className="flex flex-col gap-3 sm:flex-row"
                    >
                        {auth.user ? (
                            <Button asChild size="lg">
                                <Link href={dashboard()}>
                                    {t('nav.dashboard')}
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild size="lg">
                                    <Link href={login()}>
                                        {t('common.log_in')}
                                    </Link>
                                </Button>
                                {canRegister && (
                                    <Button asChild size="lg" variant="outline">
                                        <Link href={register()}>
                                            {t('common.register')}
                                        </Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </main>
            </div>
        </>
    );
}
