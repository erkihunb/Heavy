import { DualDate } from '@/components/dual-date';
import { useTranslation } from '@/lib/i18n';
import type { PermitTransition } from '@/types';

/**
 * The history of an application, oldest step first: what happened, when,
 * and the note that was left. Staff also see who did it.
 */
export function PermitTimeline({
    transitions,
}: {
    transitions: PermitTransition[];
}) {
    const { t } = useTranslation();

    return (
        <ol className="relative space-y-6 border-l-2 border-border pl-6">
            {transitions.map((transition, index) => (
                <li key={index} className="relative">
                    <span
                        aria-hidden="true"
                        className="absolute top-1.5 -left-[1.9rem] size-3 rounded-full border-2 border-background bg-primary"
                    />
                    <p className="font-bold">
                        {transition.from === null
                            ? t('permits.timeline.created')
                            : t(`permits.status.${transition.to}`)}
                    </p>
                    <p className="flex flex-wrap items-baseline gap-x-2 text-sm text-muted-foreground">
                        <DualDate date={transition.at} />
                        <span className="tabular-nums">{transition.time}</span>
                        {transition.actor && (
                            <span>
                                {t('permits.timeline.by', {
                                    name: transition.actor,
                                })}
                            </span>
                        )}
                    </p>
                    {transition.note && (
                        <p className="mt-2 rounded-xl bg-muted px-4 py-3 text-sm leading-relaxed whitespace-pre-line">
                            {transition.note}
                        </p>
                    )}
                </li>
            ))}
        </ol>
    );
}
