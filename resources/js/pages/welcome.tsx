import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ScanQrCode,
    ShieldCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BrandMark } from '@/components/brand-mark';
import { DualDate } from '@/components/dual-date';
import { LanguageSwitcher } from '@/components/language-switcher';
import { ProcessSteps } from '@/components/process-steps';
import { SamplePermit } from '@/components/sample-permit';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { dashboard, login, register } from '@/routes';
import type { DualDate as DualDateValue } from '@/types';

type Props = {
    canRegister?: boolean;
    sample: {
        validFrom: DualDateValue;
        validTo: DualDateValue;
    };
};

const features: {
    key: 'calendar' | 'qr' | 'security';
    icon: LucideIcon;
}[] = [
    { key: 'calendar', icon: CalendarDays },
    { key: 'qr', icon: ScanQrCode },
    { key: 'security', icon: ShieldCheck },
];

export default function Welcome({ canRegister = true, sample }: Props) {
    const { auth, today } = usePage().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('common.welcome.title')} />

            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-signal focus:px-4 focus:py-2 focus:font-semibold focus:text-signal-foreground"
            >
                {t('common.skip_to_content')}
            </a>

            <div className="flex min-h-svh flex-col bg-background text-foreground">
                <div className="hazard-stripe" aria-hidden="true" />

                <header className="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6">
                    <Link
                        href="/"
                        className="flex min-w-0 items-center gap-3 rounded-lg"
                    >
                        <BrandMark className="size-11 shrink-0" />
                        <span className="hidden min-w-0 flex-col sm:flex">
                            <span className="truncate leading-tight font-extrabold">
                                {t('common.app_name')}
                            </span>
                            <span className="truncate text-xs text-muted-foreground">
                                {t('common.authority')}
                            </span>
                        </span>
                        <span className="sr-only sm:hidden">
                            {t('common.app_name')}
                        </span>
                    </Link>

                    <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                        <LanguageSwitcher />
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>
                                    {t('nav.dashboard')}
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                asChild
                                variant="outline"
                                className="border-2 border-foreground"
                            >
                                <Link href={login()}>{t('common.log_in')}</Link>
                            </Button>
                        )}
                    </div>
                </header>

                <main id="main" className="flex-1">
                    {/* Hero */}
                    <section className="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 pt-8 pb-16 sm:px-6 lg:grid-cols-[1.15fr_0.85fr] lg:gap-16 lg:pt-14 lg:pb-24">
                        <div className="space-y-7">
                            <p className="inline-flex animate-rise items-center gap-2 rounded-full border-2 border-foreground bg-signal px-3.5 py-1.5 text-eyebrow text-signal-foreground">
                                <span
                                    className="size-2 rounded-full bg-asphalt"
                                    aria-hidden="true"
                                />
                                {t('landing.eyebrow')}
                            </p>

                            <h1 className="animate-rise text-4xl text-display [animation-delay:80ms] sm:text-5xl lg:text-6xl">
                                {t('common.welcome.title')}
                            </h1>

                            <p className="max-w-xl animate-rise text-lg leading-relaxed text-muted-foreground [animation-delay:160ms] sm:text-xl">
                                {t('common.welcome.intro')}
                            </p>

                            <div className="flex animate-rise flex-col gap-3 [animation-delay:240ms] sm:flex-row sm:items-center">
                                {auth.user ? (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="shadow-plate hover:-translate-y-0.5"
                                    >
                                        <Link href={dashboard()}>
                                            {t('nav.dashboard')}
                                            <ArrowRight aria-hidden="true" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        {canRegister && (
                                            <Button
                                                asChild
                                                size="lg"
                                                className="shadow-plate hover:-translate-y-0.5"
                                            >
                                                <Link href={register()}>
                                                    {t('common.register')}
                                                    <ArrowRight aria-hidden="true" />
                                                </Link>
                                            </Button>
                                        )}
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                            className="border-2 border-foreground"
                                        >
                                            <Link href={login()}>
                                                {t('common.log_in')}
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </div>

                            <p className="flex animate-rise flex-wrap items-center gap-x-3 gap-y-1 border-t border-border pt-5 text-sm [animation-delay:320ms]">
                                <span className="text-eyebrow text-muted-foreground">
                                    {t('common.today')}
                                </span>
                                <DualDate date={today} />
                            </p>
                        </div>

                        <div className="mx-auto w-full max-w-md animate-rise [animation-delay:200ms] lg:mx-0 lg:max-w-none">
                            <SamplePermit
                                validFrom={sample.validFrom}
                                validTo={sample.validTo}
                            />
                        </div>
                    </section>

                    {/* The route a permit takes */}
                    <section
                        aria-labelledby="process-title"
                        className="surface-asphalt"
                    >
                        <div className="mx-auto w-full max-w-6xl space-y-12 px-4 py-16 sm:px-6 lg:py-20">
                            <h2
                                id="process-title"
                                className="text-3xl text-display sm:text-4xl"
                            >
                                {t('process.title')}
                            </h2>
                            <ProcessSteps tone="asphalt" />
                        </div>
                    </section>

                    {/* What makes it trustworthy */}
                    <section
                        aria-labelledby="features-title"
                        className="mx-auto w-full max-w-6xl space-y-10 px-4 py-16 sm:px-6 lg:py-24"
                    >
                        <h2
                            id="features-title"
                            className="max-w-2xl text-3xl text-display sm:text-4xl"
                        >
                            {t('landing.features.title')}
                        </h2>

                        <ul className="grid gap-5 md:grid-cols-3">
                            {features.map(({ key, icon: Icon }, index) => (
                                <li
                                    key={key}
                                    className="group relative flex flex-col gap-4 rounded-3xl border-2 border-foreground bg-card p-6 transition-[transform,box-shadow] duration-300 ease-snap hover:-translate-y-1 hover:shadow-plate sm:p-7"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="flex size-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                                            <Icon
                                                aria-hidden="true"
                                                className="size-6"
                                            />
                                        </span>
                                        <span
                                            aria-hidden="true"
                                            className="text-4xl font-extrabold text-foreground/15 tabular-nums transition-colors duration-300 group-hover:text-signal"
                                        >
                                            0{index + 1}
                                        </span>
                                    </div>
                                    <h3 className="text-xl leading-snug font-bold">
                                        {t(`landing.features.${key}.title`)}
                                    </h3>
                                    <p className="leading-relaxed text-muted-foreground">
                                        {t(`landing.features.${key}.body`)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    </section>

                    {/* Call to action */}
                    {!auth.user && canRegister && (
                        <section
                            aria-labelledby="cta-title"
                            className="mx-auto w-full max-w-6xl px-4 pb-16 sm:px-6 lg:pb-24"
                        >
                            <div className="flex flex-col gap-6 rounded-3xl border-2 border-foreground bg-signal p-7 text-signal-foreground sm:p-10 md:flex-row md:items-center md:justify-between">
                                <div className="space-y-2">
                                    <h2
                                        id="cta-title"
                                        className="text-2xl text-display sm:text-3xl"
                                    >
                                        {t('landing.cta.title')}
                                    </h2>
                                    <p className="text-lg">
                                        {t('landing.cta.body')}
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    size="lg"
                                    className="shrink-0 bg-asphalt text-asphalt-foreground hover:bg-asphalt/90"
                                >
                                    <Link href={register()}>
                                        {t('common.register')}
                                        <ArrowRight aria-hidden="true" />
                                    </Link>
                                </Button>
                            </div>
                        </section>
                    )}
                </main>

                <footer className="surface-asphalt">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div className="flex items-center gap-3">
                            <BrandMark className="size-9 shrink-0" />
                            <div className="text-sm">
                                <p className="font-bold">
                                    {t('common.app_name')}
                                </p>
                                <p className="text-asphalt-muted">
                                    {t('common.authority')}
                                </p>
                            </div>
                        </div>
                        <DualDate
                            date={today}
                            className="text-sm"
                            secondaryClassName="text-asphalt-muted"
                        />
                    </div>
                </footer>
            </div>
        </>
    );
}
