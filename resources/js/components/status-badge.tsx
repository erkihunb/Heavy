import {
    Ban,
    CircleCheck,
    CircleDashed,
    CircleX,
    Clock,
    Hourglass,
    PencilLine,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PermitStatus } from '@/types/enums';

type Tone =
    'neutral' | 'progress' | 'attention' | 'valid' | 'stopped' | 'ended';

/**
 * How each status reads at a glance. The icon repeats what the colour says,
 * so the status never depends on colour alone.
 */
const PRESENTATION: Record<PermitStatus, { tone: Tone; icon: LucideIcon }> = {
    draft: { tone: 'neutral', icon: PencilLine },
    submitted: { tone: 'progress', icon: Clock },
    under_review: { tone: 'progress', icon: Hourglass },
    changes_requested: { tone: 'attention', icon: PencilLine },
    awaiting_payment: { tone: 'attention', icon: Clock },
    payment_submitted: { tone: 'progress', icon: Hourglass },
    payment_confirmed: { tone: 'progress', icon: Hourglass },
    issued: { tone: 'valid', icon: CircleCheck },
    active: { tone: 'valid', icon: CircleCheck },
    rejected: { tone: 'stopped', icon: CircleX },
    revoked: { tone: 'stopped', icon: Ban },
    expired: { tone: 'ended', icon: CircleDashed },
    withdrawn: { tone: 'ended', icon: CircleDashed },
};

const TONES: Record<Tone, string> = {
    // EUISIS badge tones: a tinted fill with a hairline ring.
    neutral: 'bg-muted text-foreground ring-border-strong',
    progress: 'bg-primary-light text-accent-foreground ring-primary/30',
    attention:
        'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
    valid: 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900',
    stopped:
        'bg-red-50 text-red-800 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900',
    ended: 'bg-muted text-muted-foreground ring-border',
};

/**
 * The status of a permit, named in the current language.
 */
export function StatusBadge({
    status,
    className,
}: {
    status: PermitStatus;
    className?: string;
}) {
    const { t } = useTranslation();
    const { tone, icon: Icon } = PRESENTATION[status];

    return (
        <span
            data-status={status}
            data-tone={tone}
            className={cn(
                'inline-flex w-fit items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                TONES[tone],
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-3.5 shrink-0" />
            {t(`permits.status.${status}`)}
        </span>
    );
}
