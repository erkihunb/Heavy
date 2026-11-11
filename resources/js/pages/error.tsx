import { Head, Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { home } from '@/routes';
import type { TranslationKey } from '@/types/translations';

const KNOWN = [403, 404, 405, 419, 429, 500, 503];

/**
 * The error page (EUISIS): one small card with the status as a reference, a
 * title, what happened in plain words, and a way back. A session that
 * expired (419) is fixed by reloading the page.
 */
export default function ErrorPage({ status }: { status: number }) {
    const { t } = useTranslation();
    const key = KNOWN.includes(status) ? String(status) : 'generic';
    const title =
        status === 419
            ? t('errors.session_expired_title')
            : t('errors.generic_title');
    const message = t(`errors.messages.${key}` as TranslationKey);

    return (
        <>
            <Head title={`${status} – ${title}`} />

            <main className="flex min-h-svh flex-col items-center justify-center bg-muted px-4">
                <BrandMark className="mb-6 size-10" />
                <div className="w-full max-w-md overflow-hidden rounded-panel border border-border bg-card text-center shadow-lg">
                    <div className="brand-bar" aria-hidden="true" />
                    <div className="p-8">
                        <p className="mb-2 font-mono text-xs font-semibold text-muted-foreground">
                            {t('errors.reference')} {status}
                        </p>
                        <h1 className="text-section-title">{title}</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {message}
                        </p>
                        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                            {status === 419 ? (
                                <Button
                                    onClick={() => window.location.reload()}
                                >
                                    {t('errors.reload')}
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                >
                                    {t('errors.back')}
                                </Button>
                            )}
                            <Button
                                asChild
                                variant={status === 419 ? 'outline' : 'default'}
                            >
                                <Link href={home()}>{t('errors.home')}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </main>
        </>
    );
}
