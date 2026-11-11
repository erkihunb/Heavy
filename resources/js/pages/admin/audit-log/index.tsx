import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
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
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index } from '@/routes/admin/audit-log';
import type { BreadcrumbItem, Paginated } from '@/types';
import type { AuditEntry } from '@/types/admin';
import type { TranslationKey } from '@/types/translations';

type Props = {
    entries: Paginated<AuditEntry>;
    events: string[];
    filters: { event: string | null; actor: string };
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
 * The fields of one entry: each changed field with its value before and
 * after, then anything else recorded with it.
 */
function Changes({ entry }: { entry: AuditEntry }) {
    const { t } = useTranslation();
    const fields = Array.from(
        new Set([...Object.keys(entry.old), ...Object.keys(entry.new)]),
    );
    const context = Object.entries(entry.context);

    if (fields.length === 0 && context.length === 0) {
        return null;
    }

    return (
        <dl className="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)] gap-x-3 gap-y-0.5 text-xs">
            {fields.map((field) => (
                <div key={field} className="contents">
                    <dt
                        translate="no"
                        className="font-mono text-muted-foreground"
                    >
                        {field}
                    </dt>
                    <dd className="break-words">
                        {field in entry.old && (
                            <>
                                <span className="sr-only">
                                    {t('admin.audit.before')}:{' '}
                                </span>
                                <del className="text-muted-foreground">
                                    {show(entry.old[field])}
                                </del>
                                <span aria-hidden="true"> → </span>
                            </>
                        )}
                        <span className="sr-only">
                            {t('admin.audit.after')}:{' '}
                        </span>
                        <ins className="font-medium no-underline">
                            {show(entry.new[field])}
                        </ins>
                    </dd>
                </div>
            ))}
            {context.map(([key, value]) => (
                <div key={key} className="contents">
                    <dt
                        translate="no"
                        className="font-mono text-muted-foreground"
                    >
                        {key}
                    </dt>
                    <dd className="break-words">{show(value)}</dd>
                </div>
            ))}
        </dl>
    );
}

export default function AuditLogIndex({ entries, events, filters }: Props) {
    const { t } = useTranslation();
    const [actor, setActor] = useState(filters.actor);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.audit.title'), href: index() },
    ];

    const filter = (next: { event?: string; actor?: string }) => {
        const query = {
            event: filters.event ?? '',
            actor: filters.actor,
            ...next,
        };

        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries(query).filter(([, value]) => value !== ''),
            ),
            { preserveState: true, replace: true },
        );
    };

    const submitActor = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        filter({ actor: actor.trim() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.audit.title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('admin.audit.title')}
                    description={t('admin.audit.intro')}
                />

                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid w-full gap-1.5 sm:w-64">
                        <Label htmlFor="event">
                            {t('admin.audit.filter_event')}
                        </Label>
                        <NativeSelect
                            id="event"
                            value={filters.event ?? ''}
                            onChange={(event) =>
                                filter({ event: event.target.value })
                            }
                        >
                            <option value="">
                                {t('admin.audit.any_event')}
                            </option>
                            {events.map((name) => (
                                <option key={name} value={name}>
                                    {t(
                                        `admin.audit.events.${name}` as TranslationKey,
                                    )}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>

                    <form
                        role="search"
                        onSubmit={submitActor}
                        className="flex min-w-64 flex-1 items-end gap-2"
                    >
                        <div className="grid flex-1 gap-1.5">
                            <Label htmlFor="actor">
                                {t('admin.audit.filter_actor')}
                            </Label>
                            <Input
                                id="actor"
                                type="search"
                                value={actor}
                                placeholder={t('admin.audit.actor_placeholder')}
                                onChange={(event) =>
                                    setActor(event.target.value)
                                }
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            <Search aria-hidden="true" />
                            {t('admin.audit.apply')}
                        </Button>
                    </form>
                </div>

                <TableCard>
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
                                            <Changes entry={entry} />
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
