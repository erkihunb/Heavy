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
import { badgeClassName } from '@app/ui';

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

/* The kit's status tones (soft fill, strong text), so every badge in the system matches. */
const TONES: Record<Tone, string> = {
    neutral: badgeClassName('neutral'),
    progress: badgeClassName('info'),
    attention: badgeClassName('warning'),
    valid: badgeClassName('success'),
    stopped: badgeClassName('danger'),
    ended: 'inline-flex items-center gap-1.5 rounded-badge px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset bg-muted text-muted-foreground ring-border',
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
            className={cn('w-fit', TONES[tone], className)}
        >
            <Icon aria-hidden="true" className="size-3.5 shrink-0" />
            {t(`permits.status.${status}`)}
        </span>
    );
}
