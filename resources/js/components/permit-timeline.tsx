import { Table, TBody, Td, Th, THead, Tr } from '@/components/data-table';
import { DualDate } from '@/components/dual-date';
import { useTranslation } from '@/lib/i18n';
import type { PermitTransition } from '@/types';

/**
 * The history of an application as a table, oldest step first: what
 * happened, when, and the note that was left. Staff also see who did it.
 */
export function PermitTimeline({
    transitions,
}: {
    transitions: PermitTransition[];
}) {
    const { t } = useTranslation();
    const showActor = transitions.some((transition) => transition.actor);

    return (
        <div className="overflow-hidden rounded-card border border-border bg-card">
            <Table>
                <THead>
                    <tr>
                        <Th>{t('common.date')}</Th>
                        <Th>{t('permits.timeline.step')}</Th>
                        {showActor && <Th>{t('permits.timeline.actor')}</Th>}
                        <Th>{t('permits.timeline.note')}</Th>
                    </tr>
                </THead>
                <TBody>
                    {transitions.map((transition, index) => (
                        <Tr key={index} className="align-top">
                            <Td className="align-top whitespace-nowrap">
                                <DualDate date={transition.at} />
                                <span className="block text-xs text-muted-foreground tabular-nums">
                                    {transition.time}
                                </span>
                            </Td>
                            <Td className="align-top font-medium">
                                {transition.from === null
                                    ? t('permits.timeline.created')
                                    : transition.from === transition.to
                                      ? t('permits.timeline.reissued')
                                      : t(`permits.status.${transition.to}`)}
                            </Td>
                            {showActor && (
                                <Td muted className="align-top">
                                    {transition.actor ?? '—'}
                                </Td>
                            )}
                            <Td className="max-w-md align-top whitespace-pre-line">
                                {transition.note ?? (
                                    <span className="text-muted-foreground">
                                        —
                                    </span>
                                )}
                            </Td>
                        </Tr>
                    ))}
                </TBody>
            </Table>
        </div>
    );
}
