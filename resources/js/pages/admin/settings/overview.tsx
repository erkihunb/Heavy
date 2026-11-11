import { CircleCheck, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { DualDate } from '@/components/dual-date';
import AdminSettingsLayout from '@/layouts/admin-settings-layout';
import { useTranslation } from '@/lib/i18n';
import type { DualDate as DualDateValue } from '@/types';
import type { TranslationKey } from '@/types/translations';

type Props = {
    system: {
        name: string;
        environment: string;
        debug: boolean;
        php: string;
        laravel: string;
        locale: string;
        timezone: string;
    };
    services: {
        database: string;
        cache: string;
        queue: string;
        queued_jobs: number | null;
        failed_jobs: number | null;
        mail: string;
        sms: string;
        telegram: boolean;
        pdf: string;
        error_tracking: boolean;
        two_factor: boolean;
    };
    counts: Record<string, number>;
    warnings: string[];
    lastChange: {
        by: string | null;
        at: DualDateValue | null;
        time: string | null;
    } | null;
    sections: string[];
};

function Panel({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={id}
            className="space-y-4 rounded-2xl border border-border bg-card p-5 sm:p-6"
        >
            <h2 id={id} className="text-lg font-bold">
                {title}
            </h2>
            {children}
        </section>
    );
}

export default function SettingsOverview({
    system,
    services,
    counts,
    warnings,
    lastChange,
    sections,
}: Props) {
    const { t } = useTranslation();
    const label = (key: string) =>
        t(`admin.settings.overview.labels.${key}` as TranslationKey);
    const onOff = (value: boolean) =>
        value
            ? t('admin.settings.overview.on')
            : t('admin.settings.overview.off');

    const rows = (entries: [string, ReactNode][]) => (
        <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
            {entries.map(([key, value]) => (
                <div key={key} className="contents">
                    <dt className="text-muted-foreground">{label(key)}</dt>
                    <dd translate="no" className="font-medium break-words">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );

    return (
        <AdminSettingsLayout current="overview" sections={sections}>
            <div className="flex flex-col gap-6">
                <Panel
                    id="warnings-title"
                    title={t('admin.settings.overview.warnings')}
                >
                    {warnings.length === 0 ? (
                        <p className="flex items-center gap-2 font-medium">
                            <CircleCheck
                                aria-hidden="true"
                                className="size-5 text-success"
                            />
                            {t('admin.settings.overview.all_good')}
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {warnings.map((warning) => (
                                <li
                                    key={warning}
                                    className="flex gap-2 font-medium"
                                >
                                    <TriangleAlert
                                        aria-hidden="true"
                                        className="mt-0.5 size-5 shrink-0 text-destructive"
                                    />
                                    {t(
                                        `admin.settings.warnings.${warning}` as TranslationKey,
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Panel
                        id="system-title"
                        title={t('admin.settings.overview.system')}
                    >
                        {rows([
                            ['name', system.name],
                            ['environment', system.environment],
                            ['debug', onOff(system.debug)],
                            ['php', system.php],
                            ['laravel', system.laravel],
                            ['locale', system.locale],
                            ['timezone', system.timezone],
                        ])}
                    </Panel>

                    <Panel
                        id="services-title"
                        title={t('admin.settings.overview.services')}
                    >
                        {rows([
                            [
                                'database',
                                services.database === 'down'
                                    ? t('admin.settings.overview.down')
                                    : services.database,
                            ],
                            ['cache', services.cache],
                            ['queue', services.queue],
                            [
                                'queued_jobs',
                                services.queued_jobs ??
                                    t('admin.settings.overview.unknown'),
                            ],
                            [
                                'failed_jobs',
                                services.failed_jobs ??
                                    t('admin.settings.overview.unknown'),
                            ],
                            ['mail', services.mail],
                            ['sms', services.sms],
                            ['telegram', onOff(services.telegram)],
                            ['pdf', services.pdf],
                            ['error_tracking', onOff(services.error_tracking)],
                            ['two_factor', onOff(services.two_factor)],
                        ])}
                    </Panel>
                </div>

                <Panel
                    id="counts-title"
                    title={t('admin.settings.overview.counts')}
                >
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {Object.entries(counts).map(([key, value]) => (
                            <li
                                key={key}
                                className="rounded-xl border border-border p-4"
                            >
                                <p className="text-eyebrow text-muted-foreground">
                                    {label(key)}
                                </p>
                                <p className="mt-1 text-2xl font-extrabold tabular-nums">
                                    {value}
                                </p>
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel
                    id="last-change-title"
                    title={t('admin.settings.overview.last_change')}
                >
                    {lastChange?.at ? (
                        <p className="flex flex-wrap items-baseline gap-2 text-sm">
                            <DualDate date={lastChange.at} />
                            <span className="tabular-nums">
                                {lastChange.time}
                            </span>
                            {lastChange.by && (
                                <span className="text-muted-foreground">
                                    {t('admin.settings.overview.by', {
                                        name: lastChange.by,
                                    })}
                                </span>
                            )}
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('admin.settings.overview.never_changed')}
                        </p>
                    )}
                </Panel>
            </div>
        </AdminSettingsLayout>
    );
}
