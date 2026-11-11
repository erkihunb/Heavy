import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    Globe,
    LayoutDashboard,
    Mail,
    MessageSquare,
    Palette,
    Send,
    Settings2,
    ShieldCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { overview, show } from '@/routes/admin/settings';
import type { BreadcrumbItem } from '@/types';
import type { TranslationKey } from '@/types/translations';

const ICONS: Record<string, LucideIcon> = {
    overview: LayoutDashboard,
    general: Settings2,
    appearance: Palette,
    notifications: Bell,
    email: Mail,
    sms: MessageSquare,
    telegram: Send,
    security: ShieldCheck,
    localization: Globe,
};

/**
 * The System settings area: a menu of sections beside the current one.
 */
export default function AdminSettingsLayout({
    current,
    sections,
    children,
}: {
    current: string;
    sections: string[];
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const title = t(`admin.settings.sections.${current}` as TranslationKey);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.settings.title'), href: overview() },
        ...(current === 'overview'
            ? []
            : [{ title, href: show({ section: current }) }]),
    ];

    const items = ['overview', ...sections];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${title} · ${t('admin.settings.title')}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('admin.settings.title')}
                    description={t('admin.settings.intro')}
                />

                <div className="flex flex-col gap-6 lg:flex-row lg:gap-10">
                    <nav
                        aria-label={t('admin.settings.title')}
                        className="lg:w-56 lg:shrink-0"
                    >
                        <ul className="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0">
                            {items.map((section) => {
                                const Icon = ICONS[section] ?? Settings2;
                                const active = section === current;

                                return (
                                    <li key={section} className="shrink-0">
                                        <Link
                                            href={
                                                section === 'overview'
                                                    ? overview()
                                                    : show({ section })
                                            }
                                            aria-current={
                                                active ? 'page' : undefined
                                            }
                                            className={cn(
                                                'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors hover:bg-muted',
                                                active &&
                                                    'bg-primary font-bold text-primary-foreground hover:bg-primary',
                                            )}
                                        >
                                            <Icon
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                            {t(
                                                `admin.settings.sections.${section}` as TranslationKey,
                                            )}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </nav>

                    <div className="min-w-0 flex-1">{children}</div>
                </div>
            </div>
        </AppLayout>
    );
}
