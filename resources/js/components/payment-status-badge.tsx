import { CircleCheck, CircleX, Hourglass } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PaymentStatus } from '@/types/enums';
import { badgeClassName } from '@app/ui';

const PRESENTATION: Record<
    PaymentStatus,
    { icon: LucideIcon; className: string }
> = {
    submitted: {
        icon: Hourglass,
        className: badgeClassName('warning'),
    },
    verified: {
        icon: CircleCheck,
        className: badgeClassName('success'),
    },
    rejected: {
        icon: CircleX,
        className: badgeClassName('danger'),
    },
};

/**
 * Where one payment stands, named in the current language. The icon repeats
 * what the colour says.
 */
export function PaymentStatusBadge({
    status,
    className,
}: {
    status: PaymentStatus;
    className?: string;
}) {
    const { t } = useTranslation();
    const { icon: Icon, className: tone } = PRESENTATION[status];

    return (
        <span data-status={status} className={cn('w-fit', tone, className)}>
            <Icon aria-hidden="true" className="size-3.5 shrink-0" />
            {t(`payments.status.${status}`)}
        </span>
    );
}
