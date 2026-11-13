import { usePage } from '@inertiajs/react';
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
 * Nothing here animates. The page is drawn once and stays still: somebody
 * is reading a form and typing a password, and movement behind that only
 * gets in the way.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslation();
    const { branding, appearance } = usePage().props;
    const loginMessage = branding?.loginMessage;

    return (
        <div className="relative flex min-h-svh flex-col bg-muted text-foreground lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            {/* ── Brand panel: the service, in the Authority's colours ── */}
            <aside className="relative isolate overflow-hidden surface-brand px-5 py-7 sm:px-8 lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-12">
                <AuthPanelBackdrop />

                <div className="flex items-center gap-3">
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
                    <p className="text-eyebrow text-white/90">
                        {t('auth_ui.panel.eyebrow')}
                    </p>
                    <h2 className="mt-3 max-w-md text-3xl leading-tight font-bold text-balance">
                        {t('auth_ui.panel.title')}
                    </h2>
                    <p className="mt-4 max-w-md text-sm leading-relaxed text-white/90">
                        {t('auth_ui.panel.intro')}
                    </p>

                    <ul className="mt-9 max-w-md space-y-4">
                        {(['apply', 'decide', 'verify'] as const).map(
                            (step, index) => (
                                <li key={step} className="flex gap-3.5">
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

                <p className="mt-10 hidden max-w-md border-t border-white/20 pt-5 text-xs leading-relaxed text-white/90 lg:block">
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
                    <div className="w-full max-w-md overflow-hidden rounded-panel border border-border bg-card shadow-lg">
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

                    <div className="mt-7 flex w-full max-w-md flex-col items-center gap-1.5 text-center text-xs text-muted-foreground">
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
 * The panel's background: a plain brand surface, lit a little from the top
 * start corner so it does not read as a flat block of colour. Decorative,
 * not announced, and entirely still — nothing moves behind the form.
 */
function AuthPanelBackdrop() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(90%_70%_at_15%_0%,rgb(255_255_255/0.16),transparent_70%)]"
        />
    );
}
