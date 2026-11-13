import { usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { HeavyVehicleMotion } from '@/components/auth/heavy-vehicle-motion';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { SupportContacts } from '@/components/support-contacts';
import { ThemeToggle } from '@/components/theme-toggle';
import { useTranslation } from '@/lib/i18n';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in, registration and password pages: on a wide screen the form sits
 * beside a brand panel that carries the Authority's mark, its name and the
 * system's name in its centre, and no other text (the owner's decision). On
 * a narrow screen the panel becomes a short header above the form.
 *
 * The site root redirects here, so this is the first page of the system:
 * it names the Authority, the service and the way in, and nothing else.
 *
 * `still` draws the page with no entrance at all. The sign-in page passes
 * it: it is the page people meet first and return to daily, and it should
 * simply be there. The password, verification and two-factor pages, which
 * are reached once in a while and are mostly a short instruction, keep the
 * entrance that fades each piece up in reading order (auth-reveal in
 * resources/css/app.css, itself off under reduced motion).
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
    still = false,
}: AuthLayoutProps & { still?: boolean }) {
    const { t } = useTranslation();
    const { branding, appearance } = usePage().props;
    const loginMessage = branding?.loginMessage;

    /* The entrance, or nothing at all when the page asked to be still. */
    const reveal = (
        ms: number,
    ): { className: string; style?: CSSProperties } =>
        still
            ? { className: '' }
            : {
                  className: 'auth-reveal',
                  style: { '--auth-delay': `${ms}ms` } as CSSProperties,
              };

    return (
        <div className="relative flex min-h-svh flex-col bg-muted text-foreground lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            {/* ── Brand panel: the service, in the Authority's colours ── */}
            <aside className="relative isolate overflow-hidden surface-brand px-5 py-7 sm:px-8 lg:flex lg:flex-col lg:items-center lg:justify-center lg:px-12 lg:pt-12 lg:pb-[26svh]">
                <AuthPanelBackdrop />

                {/* The sign-in page's moving picture: heavy vehicles crossing
                    the city along the foot of the panel, clouds across its
                    top. The centre stays clear for the mark and the names. */}
                {still && <HeavyVehicleMotion />}

                {/* The Authority's mark, its name and the system's name: a
                    short row above the form on a narrow screen, and the
                    centre of the panel on a wide one. Nothing else is said
                    here. */}
                <div
                    className={`flex items-center gap-3 lg:flex-col lg:gap-0 lg:text-center ${reveal(0).className}`}
                    style={reveal(0).style}
                >
                    <span className="flex size-11 shrink-0 items-center justify-center lg:size-28">
                        <BrandMark className="size-full" />
                    </span>
                    <span className="flex min-w-0 flex-col-reverse lg:mt-7 lg:max-w-md lg:flex-col">
                        <span className="mt-0.5 block text-xs text-white/90 lg:mt-0 lg:text-2xl lg:leading-snug lg:font-bold lg:text-balance lg:text-white">
                            {t('common.authority')}
                        </span>
                        <span className="block text-sm leading-tight font-bold text-balance lg:mt-3 lg:text-base lg:font-medium lg:text-white/90">
                            {t('common.app_name')}
                        </span>
                    </span>
                </div>
            </aside>

            {/* ── The form itself ── */}
            <div className="flex flex-1 flex-col">
                <header className="flex items-center justify-end gap-1.5 px-4 py-3 sm:px-6">
                    <ThemeToggle className="flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-card hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none" />
                    {appearance.showLanguageSwitcher && <LanguageSwitcher />}
                </header>

                <main className="flex flex-1 flex-col items-center justify-center px-4 pb-10 sm:px-6">
                    <div
                        className={`w-full max-w-md overflow-hidden rounded-panel border border-border bg-card shadow-lg ${reveal(80).className}`}
                        style={reveal(80).style}
                    >
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

                    <div
                        className={`mt-7 flex w-full max-w-md flex-col items-center gap-1.5 text-center text-xs text-muted-foreground ${reveal(200).className}`}
                        style={reveal(200).style}
                    >
                        {loginMessage && (
                            <p className="whitespace-pre-line">
                                {loginMessage}
                            </p>
                        )}
                        <SupportContacts className="justify-center" />
                    </div>
                </main>
            </div>
        </div>
    );
}

/**
 * The panel's background: a plain brand surface, lit a little from its top
 * start corner so it does not read as a flat block of colour.
 *
 * Nothing is drawn behind the text. Three decorative backgrounds were tried
 * here and removed (docs/DECISIONS.md); the sign-in page's picture is the
 * city and its heavy vehicles along the panel's foot, clear of the names.
 */
function AuthPanelBackdrop() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(90%_70%_at_15%_0%,rgb(255_255_255/0.16),transparent_70%)]"
        />
    );
}
