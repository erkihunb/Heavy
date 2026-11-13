import { usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { BrandMark } from '@/components/brand-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { SupportContacts } from '@/components/support-contacts';
import { ThemeToggle } from '@/components/theme-toggle';
import { useTranslation } from '@/lib/i18n';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in, registration and password pages: on a wide screen the form sits
 * beside a brand panel that says what the system is for and where an account
 * comes from (nobody signs up: accounts are opened at the office). On a
 * narrow screen the panel becomes a short header above the form.
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
 *
 * `scene` is an illustration to draw behind the panel. The sign-in page
 * passes one; the rest leave the panel plain.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
    still = false,
    scene,
}: AuthLayoutProps & { still?: boolean; scene?: ReactNode }) {
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
            <aside className="relative isolate overflow-hidden surface-brand px-5 py-7 sm:px-8 lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-12">
                <AuthPanelBackdrop scene={scene} />

                <div
                    className={`flex items-center gap-3 ${reveal(0).className}`}
                    style={reveal(0).style}
                >
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-card bg-white/10 p-1.5 ring-1 ring-white/25 lg:size-14">
                        <BrandMark className="size-full" />
                    </span>
                    <span className="min-w-0">
                        <span className="block text-sm leading-tight font-bold text-balance lg:text-base">
                            {t('common.app_name')}
                        </span>
                        <span className="mt-0.5 block text-xs text-white/90">
                            {t('common.authority')}
                        </span>
                    </span>
                </div>

                {/* The pitch and the three facts, shown only where there is room. */}
                <div className="mt-10 hidden lg:block">
                    <p
                        className={`text-eyebrow text-white/90 ${reveal(60).className}`}
                        style={reveal(60).style}
                    >
                        {t('auth_ui.panel.eyebrow')}
                    </p>
                    <h2
                        className={`mt-3 max-w-md text-3xl leading-tight font-bold text-balance ${reveal(110).className}`}
                        style={reveal(110).style}
                    >
                        {t('auth_ui.panel.title')}
                    </h2>
                    <p
                        className={`mt-4 max-w-md text-sm leading-relaxed text-white/90 ${reveal(160).className}`}
                        style={reveal(160).style}
                    >
                        {t('auth_ui.panel.intro')}
                    </p>

                    <ul className="mt-9 max-w-md space-y-4">
                        {(['apply', 'decide', 'verify'] as const).map(
                            (step, index) => (
                                <li
                                    key={step}
                                    className={`flex gap-3.5 ${reveal(220 + index * 70).className}`}
                                    style={reveal(220 + index * 70).style}
                                >
                                    <span
                                        aria-hidden="true"
                                        className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-card bg-white/10 text-xs font-bold ring-1 ring-white/25"
                                    >
                                        {index + 1}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block text-sm font-semibold">
                                            {t(
                                                `auth_ui.panel.steps.${step}.title`,
                                            )}
                                        </span>
                                        <span className="mt-0.5 block text-xs leading-relaxed text-white/90">
                                            {t(
                                                `auth_ui.panel.steps.${step}.body`,
                                            )}
                                        </span>
                                    </span>
                                </li>
                            ),
                        )}
                    </ul>
                </div>

                {/* The system's own name, in both languages. */}
                <div
                    className={`mt-10 hidden max-w-md border-t border-white/20 pt-5 lg:block ${reveal(420).className}`}
                    style={reveal(420).style}
                >
                    <p
                        lang="am"
                        className="text-sm leading-relaxed font-semibold text-balance"
                    >
                        {t('auth_ui.hero.title')}
                    </p>
                    <p lang="en" className="mt-1 text-xs text-white/90">
                        {t('auth_ui.hero.subtitle')}
                    </p>
                </div>

                <p
                    className={`mt-5 hidden max-w-md text-xs leading-relaxed text-white/90 lg:block ${reveal(460).className}`}
                    style={reveal(460).style}
                >
                    {t('auth_ui.panel.no_account')}
                </p>
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
 * The panel's background. `scene` is the sign-in illustration, passed in by
 * the page that wants one; without it the panel is a plain brand surface
 * with the light in its top start corner.
 *
 * Decorative throughout: the scene marks itself aria-hidden, and nothing
 * here is announced.
 */
function AuthPanelBackdrop({ scene }: { scene?: ReactNode }) {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
        >
            {scene}
            <div className="absolute inset-0 bg-[radial-gradient(90%_70%_at_15%_0%,rgb(255_255_255/0.16),transparent_70%)]" />
        </div>
    );
}
