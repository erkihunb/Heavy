import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    EmptyRow,
    Table,
    TableCard,
    TBody,
    Td,
    Th,
    THead,
    Tr,
} from '@/components/data-table';
import { DualDate } from '@/components/dual-date';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index } from '@/routes/admin/audit-log';
import { FilterField, FilterPanel, Input, Select } from '@/shell/Components/ui';
import type { BreadcrumbItem, Paginated } from '@/types';
import type { AuditEntry } from '@/types/admin';
import type { TranslationKey } from '@/types/translations';

type Filters = {
    event: string | null;
    actor: string;
    subject: string;
    from: string | null;
    to: string | null;
};

type Props = {
    entries: Paginated<AuditEntry>;
    /** The events by area: permit, payment, user, settings… */
    eventGroups: Record<string, string[]>;
    filters: Filters;
};

function show(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? '✓' : '✗';
    }

    if (Array.isArray(value)) {
        return value.length === 0 ? '—' : value.map(show).join(', ');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

/**
 * One entry's details, folded away until opened: each changed field by its
 * label, old → new, then what else was recorded with it.
 */
function Details({ entry }: { entry: AuditEntry }) {
    const { t } = useTranslation();
    const count = entry.changes.length;

    if (count === 0 && entry.context.length === 0) {
        return (
            <span className="text-xs text-muted-foreground">
                {t('admin.audit.no_details')}
            </span>
        );
    }

    return (
        <details className="group">
            <summary className="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-control px-2 py-1 text-sm font-medium text-primary hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                <ChevronDown
                    aria-hidden="true"
                    className="size-4 transition-transform group-open:rotate-180"
                />
                {count > 0
                    ? t('admin.audit.changes_count', { count })
                    : t('admin.audit.show_details')}
                {count === 0 && entry.context[0] && (
                    <span className="font-normal text-muted-foreground">
                        · {show(entry.context[0].value)}
                    </span>
                )}
            </summary>

            <div className="mt-2 overflow-hidden rounded-control border border-border">
                {count > 0 && (
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-1.5 text-left font-medium">
                                    {t('admin.audit.field')}
                                </th>
                                <th className="px-3 py-1.5 text-left font-medium">
                                    {t('admin.audit.before')}
                                </th>
                                <th className="px-3 py-1.5 text-left font-medium">
                                    {t('admin.audit.after')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {entry.changes.map((change) => (
                                <tr key={change.key}>
                                    <th
                                        scope="row"
                                        title={change.key}
                                        className="px-3 py-1.5 text-left font-medium"
                                    >
                                        {change.label}
                                    </th>
                                    <td className="px-3 py-1.5 break-words text-muted-foreground">
                                        {change.had_old ? (
                                            <del>{show(change.old)}</del>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-3 py-1.5 font-semibold break-words">
                                        {show(change.new)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
                {entry.context.length > 0 && (
                    <dl className="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)] gap-x-3 gap-y-1 border-t border-border bg-muted/30 px-3 py-2 text-xs first:border-t-0">
                        {entry.context.map((item) => (
                            <div key={item.key} className="contents">
                                <dt
                                    title={item.key}
                                    className="text-muted-foreground"
                                >
                                    {item.label}
                                </dt>
                                <dd className="font-medium break-words">
                                    {show(item.value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
            </div>
        </details>
    );
}

export default function AuditLogIndex({
    entries,
    eventGroups,
    filters,
}: Props) {
    const { t } = useTranslation();
    const [actor, setActor] = useState(filters.actor);
    const [subject, setSubject] = useState(filters.subject);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.audit.title'), href: index() },
    ];

    const filter = (next: Partial<Filters>) => {
        const query = {
            event: filters.event ?? '',
            actor: filters.actor,
            subject: filters.subject,
            from: filters.from ?? '',
            to: filters.to ?? '',
            ...next,
        };

        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries(query).filter(
                    ([, value]) => value !== '' && value !== null,
                ),
            ),
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        filter({ actor: actor.trim(), subject: subject.trim() });
    };

    const filtered =
        filters.event !== null ||
        filters.actor !== '' ||
        filters.subject !== '' ||
        filters.from !== null ||
        filters.to !== null;

    const clear = () => {
        setActor('');
        setSubject('');
        router.get(index.url(), {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.audit.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('admin.audit.title')}
                    description={t('admin.audit.intro')}
                />

                <FilterPanel
                    label={t('admin.audit.title')}
                    onSubmit={submitSearch}
                    onReset={clear}
                    hasActiveFilters={filtered}
                    submitLabel={t('admin.audit.apply')}
                    resetLabel={t('admin.audit.clear')}
                >
                    <FilterField label={t('admin.audit.filter_event')}>
                        <Select
                            id="event"
                            value={filters.event ?? ''}
                            onChange={(event) =>
                                filter({ event: event.target.value })
                            }
                        >
                            <option value="">
                                {t('admin.audit.any_event')}
                            </option>
                            {Object.entries(eventGroups).map(
                                ([group, events]) => (
                                    <optgroup
                                        key={group}
                                        label={t(
                                            `admin.audit.groups.${group}` as TranslationKey,
                                        )}
                                    >
                                        {events.map((name) => (
                                            <option key={name} value={name}>
                                                {t(
                                                    `admin.audit.events.${name}` as TranslationKey,
                                                )}
                                            </option>
                                        ))}
                                    </optgroup>
                                ),
                            )}
                        </Select>
                    </FilterField>
                    <FilterField label={t('admin.audit.filter_subject')} wide>
                        <Input
                            id="subject"
                            type="search"
                            value={subject}
                            translate="no"
                            placeholder={t('admin.audit.subject_placeholder')}
                            onChange={(event) => setSubject(event.target.value)}
                        />
                    </FilterField>
                    <FilterField label={t('admin.audit.filter_actor')} wide>
                        <Input
                            id="actor"
                            type="search"
                            value={actor}
                            placeholder={t('admin.audit.actor_placeholder')}
                            onChange={(event) => setActor(event.target.value)}
                        />
                    </FilterField>
                    <FilterField label={t('admin.audit.from')}>
                        <Input
                            id="from"
                            type="date"
                            value={filters.from ?? ''}
                            max={filters.to ?? undefined}
                            onChange={(event) =>
                                filter({ from: event.target.value })
                            }
                        />
                    </FilterField>
                    <FilterField label={t('admin.audit.to')}>
                        <Input
                            id="to"
                            type="date"
                            value={filters.to ?? ''}
                            min={filters.from ?? undefined}
                            onChange={(event) =>
                                filter({ to: event.target.value })
                            }
                        />
                    </FilterField>
                </FilterPanel>

                <TableCard toolsId="audit-log">
                    <Table>
                        <THead>
                            <tr>
                                <Th>{t('common.date')}</Th>
                                <Th>{t('admin.audit.filter_event')}</Th>
                                <Th>{t('admin.audit.subject')}</Th>
                                <Th>{t('admin.audit.filter_actor')}</Th>
                                <Th>{t('admin.audit.details')}</Th>
                            </tr>
                        </THead>
                        <TBody>
                            {entries.data.length === 0 ? (
                                <EmptyRow colSpan={5}>
                                    {t('admin.audit.empty')}
                                </EmptyRow>
                            ) : (
                                entries.data.map((entry) => (
                                    <Tr key={entry.id} className="align-top">
                                        <Td className="align-top whitespace-nowrap">
                                            {entry.created_at && (
                                                <>
                                                    <DualDate
                                                        date={entry.created_at}
                                                    />
                                                    <span className="block text-xs text-muted-foreground tabular-nums">
                                                        {entry.created_time}
                                                    </span>
                                                </>
                                            )}
                                        </Td>
                                        <Td className="align-top font-medium">
                                            {t(
                                                `admin.audit.events.${entry.event}` as TranslationKey,
                                            )}
                                        </Td>
                                        <Td className="align-top">
                                            {entry.subject ? (
                                                <>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {t(
                                                            `admin.audit.subjects.${entry.subject.type}`,
                                                        )}
                                                    </span>
                                                    {entry.subject.url ? (
                                                        <Link
                                                            href={
                                                                entry.subject
                                                                    .url
                                                            }
                                                            translate="no"
                                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                                        >
                                                            {
                                                                entry.subject
                                                                    .label
                                                            }
                                                        </Link>
                                                    ) : (
                                                        <span translate="no">
                                                            {
                                                                entry.subject
                                                                    .label
                                                            }
                                                        </span>
                                                    )}
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </Td>
                                        <Td className="align-top">
                                            {entry.actor ? (
                                                <>
                                                    <span className="block">
                                                        {entry.actor.name}
                                                    </span>
                                                    <span
                                                        translate="no"
                                                        className="block text-xs text-muted-foreground"
                                                    >
                                                        {entry.actor.email}
                                                    </span>
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {t('admin.audit.system')}
                                                </span>
                                            )}
                                        </Td>
                                        <Td className="min-w-72 align-top">
                                            <Details entry={entry} />
                                        </Td>
                                    </Tr>
                                ))
                            )}
                        </TBody>
                    </Table>
                </TableCard>

                <Pagination links={entries.links} meta={entries.meta} />
            </div>
        </AppLayout>
    );
}
