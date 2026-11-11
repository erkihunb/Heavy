import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CircleAlert,
    Info,
    MailCheck,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import { ProcessSteps } from '@/components/process-steps';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show as organization } from '@/routes/organization';
import { show as twoFactor } from '@/routes/two-factor';
import { create as registerVehicle } from '@/routes/vehicles';
import type { BreadcrumbItem } from '@/types';

type Props = {
    account: {
        emailVerified: boolean;
        twoFactorEnabled: boolean;
    };
    /** Where an applicant stands in setting up their register; null for staff. */
    registration: {
        hasOrganization: boolean;
        vehicleCount: number;
    } | null;
};

function StatusLine({
    ok,
    okIcon: OkIcon,
    children,
}: {
    ok: boolean;
    okIcon: typeof MailCheck;
    children: React.ReactNode;
}) {
    const Icon = ok ? OkIcon : CircleAlert;

    return (
        <li className="flex items-center gap-3">
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                    ok
                        ? 'bg-accent text-accent-foreground'
                        : 'bg-signal text-signal-foreground',
                )}
            >
                <Icon aria-hidden="true" className="size-5" />
            </span>
            <span className="font-medium">{children}</span>
        </li>
    );
}

export default function Dashboard({ account, registration }: Props) {
    const { auth, today } = usePage().props;
    const { t } = useTranslation();
    const role = auth.user.roles[0] ?? 'none';

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('nav.dashboard')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-6">
                {/* Greeting */}
                <section className="flex animate-rise flex-col gap-6 rounded-3xl border border-transparent surface-asphalt p-6 sm:p-8 dark:border-border">
                    <div className="space-y-4">
                        <p className="inline-flex items-center gap-2 rounded-full bg-signal px-3 py-1.5 text-eyebrow text-signal-foreground">
                            <UserRound
                                aria-hidden="true"
                                className="size-3.5"
                            />
                            {t(`roles.${role}.name`)}
                        </p>
                        <h1 className="text-3xl text-display sm:text-4xl">
                            {t('dashboard.greeting', { name: auth.user.name })}
                        </h1>
                        <div
                            className="hidden lane-dash w-48 text-signal md:block"
                            aria-hidden="true"
                        />
                    </div>

                    <div className="space-y-1 md:hidden">
                        <p className="text-eyebrow text-signal">
                            {t('common.today')}
                        </p>
                        <p className="text-2xl font-extrabold">
                            <time dateTime={today.gregorian}>
                                {today.ethiopian_label}
                            </time>
                        </p>
                        <p className="text-sm text-asphalt-muted tabular-nums">
                            {today.gregorian}
                        </p>
                    </div>
                </section>

                {/* The applicant's next step */}
                {registration && (
                    <section
                        aria-labelledby="next-title"
                        className="flex animate-rise flex-col gap-5 rounded-3xl border-2 border-foreground bg-card p-6 [animation-delay:60ms] sm:flex-row sm:items-center sm:justify-between sm:p-7"
                    >
                        <div className="space-y-1.5">
                            <p className="text-eyebrow text-muted-foreground">
                                {t('dashboard.next.label')}
                            </p>
                            <h2
                                id="next-title"
                                className="text-2xl text-display"
                            >
                                {registration.hasOrganization
                                    ? t('dashboard.next.vehicles.title')
                                    : t('dashboard.next.organization.title')}
                            </h2>
                            <p className="text-muted-foreground">
                                {registration.hasOrganization
                                    ? t('dashboard.next.vehicles.body', {
                                          count: registration.vehicleCount,
                                      })
                                    : t('dashboard.next.organization.body')}
                            </p>
                        </div>
                        <Button
                            asChild
                            size="lg"
                            className="shrink-0 shadow-plate hover:-translate-y-0.5"
                        >
                            {registration.hasOrganization ? (
                                <Link href={registerVehicle()}>
                                    {t('vehicles.register')}
                                    <ArrowRight aria-hidden="true" />
                                </Link>
                            ) : (
                                <Link href={organization()}>
                                    {t('organization.actions.create')}
                                    <ArrowRight aria-hidden="true" />
                                </Link>
                            )}
                        </Button>
                    </section>
                )}

                {/* What is not available yet */}
                <section
                    aria-labelledby="notice-title"
                    className="flex animate-rise gap-4 rounded-2xl border-2 border-foreground bg-signal/20 p-5 [animation-delay:80ms]"
                >
                    <Info
                        aria-hidden="true"
                        className="mt-0.5 size-5 shrink-0"
                    />
                    <div className="space-y-1">
                        <h2 id="notice-title" className="font-bold">
                            {t('dashboard.notice.title')}
                        </h2>
                        <p className="text-sm leading-relaxed">
                            {t('dashboard.notice.body')}
                        </p>
                    </div>
                </section>

                <div className="grid animate-rise gap-5 [animation-delay:160ms] md:grid-cols-2">
                    {/* Role */}
                    <section
                        aria-labelledby="role-title"
                        className="space-y-3 rounded-3xl border border-border bg-card p-6 sm:p-7"
                    >
                        <p className="text-eyebrow text-muted-foreground">
                            {t('dashboard.role_label')}
                        </p>
                        <h2 id="role-title" className="text-2xl text-display">
                            {t(`roles.${role}.name`)}
                        </h2>
                        <p className="leading-relaxed text-muted-foreground">
                            {t(`roles.${role}.summary`)}
                        </p>
                    </section>

                    {/* Account */}
                    <section
                        aria-labelledby="account-title"
                        className="flex flex-col gap-5 rounded-3xl border border-border bg-card p-6 sm:p-7"
                    >
                        <h2
                            id="account-title"
                            className="text-eyebrow text-muted-foreground"
                        >
                            {t('dashboard.account.title')}
                        </h2>
                        <ul className="space-y-3">
                            <StatusLine
                                ok={account.emailVerified}
                                okIcon={MailCheck}
                            >
                                {account.emailVerified
                                    ? t('dashboard.account.email_verified')
                                    : t('dashboard.account.email_unverified')}
                            </StatusLine>
                            <StatusLine
                                ok={account.twoFactorEnabled}
                                okIcon={ShieldCheck}
                            >
                                {account.twoFactorEnabled
                                    ? t('dashboard.account.two_factor_on')
                                    : t('dashboard.account.two_factor_off')}
                            </StatusLine>
                        </ul>
                        <Button
                            asChild
                            variant="outline"
                            className="mt-auto w-fit"
                        >
                            <Link href={twoFactor()}>
                                {t('dashboard.account.manage')}
                            </Link>
                        </Button>
                    </section>
                </div>

                {/* The process */}
                <section
                    aria-labelledby="process-title"
                    className="animate-rise space-y-8 rounded-3xl border border-border bg-card p-6 [animation-delay:240ms] sm:p-8"
                >
                    <h2 id="process-title" className="text-2xl text-display">
                        {t('process.title')}
                    </h2>
                    <ProcessSteps />
                </section>
            </div>
        </AppLayout>
    );
}
