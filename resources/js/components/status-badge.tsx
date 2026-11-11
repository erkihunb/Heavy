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
    issued: { tone: 'valid', icon: CircleCheck },
    active: { tone: 'valid', icon: CircleCheck },
    rejected: { tone: 'stopped', icon: CircleX },
    revoked: { tone: 'stopped', icon: Ban },
    expired: { tone: 'ended', icon: CircleDashed },
    withdrawn: { tone: 'ended', icon: CircleDashed },
};

const TONES: Record<Tone, string> = {
    neutral: 'border-border bg-card text-foreground',
    progress: 'border-transparent bg-secondary text-secondary-foreground',
    attention: 'border-transparent bg-highlight text-highlight-foreground',
    valid: 'border-transparent bg-success text-success-foreground',
    stopped: 'border-transparent bg-destructive text-white',
    ended: 'border-border bg-muted text-muted-foreground',
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
                'inline-flex w-fit items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-bold whitespace-nowrap',
                TONES[tone],
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-4 shrink-0" />
            {t(`permits.status.${status}`)}
        </span>
    );
}
