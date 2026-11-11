import { CircleCheck, CircleX, Hourglass } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PaymentStatus } from '@/types/enums';

const PRESENTATION: Record<
    PaymentStatus,
    { icon: LucideIcon; className: string }
> = {
    submitted: {
        icon: Hourglass,
        className: 'border-transparent bg-highlight text-highlight-foreground',
    },
    verified: {
        icon: CircleCheck,
        className: 'border-transparent bg-success text-success-foreground',
    },
    rejected: {
        icon: CircleX,
        className: 'border-transparent bg-destructive text-white',
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
        <span
            data-status={status}
            className={cn(
                'inline-flex w-fit items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-bold whitespace-nowrap',
                tone,
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-4 shrink-0" />
            {t(`payments.status.${status}`)}
        </span>
    );
}
