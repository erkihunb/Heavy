import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    FileText,
    Globe,
    Hash,
    LayoutDashboard,
    Mail,
    MessageSquare,
    Palette,
    Send,
    Settings2,
    ShieldCheck,
    SlidersHorizontal,
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
    localization: Globe,
    permits: SlidersHorizontal,
    codes: Hash,
    documents: FileText,
    notifications: Bell,
    email: Mail,
    sms: MessageSquare,
    telegram: Send,
    security: ShieldCheck,
};

function SectionLink({
    section,
    active,
    compact = false,
}: {
    section: string;
    active: boolean;
    compact?: boolean;
}) {
    const { t } = useTranslation();
    const Icon = ICONS[section] ?? Settings2;

    return (
        <Link
            href={section === 'overview' ? overview() : show({ section })}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex items-center gap-2.5 rounded-card text-sm font-medium whitespace-nowrap transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                compact ? 'border border-border px-3 py-1.5' : 'px-3 py-2',
                active &&
                    'border-primary bg-primary font-bold text-primary-foreground hover:bg-primary',
            )}
        >
            <Icon aria-hidden="true" className="size-4 shrink-0" />
            {t(`admin.settings.sections.${section}` as TranslationKey)}
        </Link>
    );
}

/**
 * The System settings area: a menu of sections, grouped by category
 * (Organisation, Permits, Communication, Access), beside the current one. On a
 * narrow screen the sections become one row that scrolls sideways.
 */
export default function AdminSettingsLayout({
    current,
    sections,
    categories,
    children,
}: {
    current: string;
    sections: string[];
    categories: Record<string, string[]>;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const title = t(`admin.settings.sections.${current}` as TranslationKey);
    const currentCategory = Object.entries(categories).find(([, list]) =>
        list.includes(current),
    )?.[0];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.settings.title'), href: overview() },
        ...(current === 'overview'
            ? []
            : [{ title, href: show({ section: current }) }]),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${title} · ${t('admin.settings.title')}`} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('admin.settings.title')}
                    description={t('admin.settings.intro')}
                />

                <div className="flex flex-col gap-6 lg:flex-row lg:gap-10">
                    <nav
                        aria-label={t('admin.settings.title')}
                        className="lg:w-60 lg:shrink-0"
                    >
                        {/* Narrow screens: one sideways-scrolling row. */}
                        <ul className="flex gap-2 overflow-x-auto pb-1 lg:hidden">
                            {['overview', ...sections].map((section) => (
                                <li key={section} className="shrink-0">
                                    <SectionLink
                                        section={section}
                                        active={section === current}
                                        compact
                                    />
                                </li>
                            ))}
                        </ul>

                        {/* Wide screens: the sections under their categories. */}
                        <div className="hidden flex-col gap-5 lg:flex">
                            <SectionLink
                                section="overview"
                                active={current === 'overview'}
                            />

                            {Object.entries(categories).map(
                                ([category, list]) => (
                                    <div key={category}>
                                        <p
                                            className={cn(
                                                'mb-1.5 px-3 text-xs font-bold tracking-wide text-muted-foreground uppercase',
                                                category === currentCategory &&
                                                    'text-primary',
                                            )}
                                        >
                                            {t(
                                                `admin.settings.categories.${category}` as TranslationKey,
                                            )}
                                        </p>
                                        <ul className="flex flex-col gap-0.5 border-l border-border pl-2">
                                            {list.map((section) => (
                                                <li key={section}>
                                                    <SectionLink
                                                        section={section}
                                                        active={
                                                            section === current
                                                        }
                                                    />
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ),
                            )}
                        </div>
                    </nav>

                    <div className="min-w-0 flex-1">{children}</div>
                </div>
            </div>
        </AppLayout>
    );
}
