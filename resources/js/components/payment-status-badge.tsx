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
        className:
            'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
    },
    verified: {
        icon: CircleCheck,
        className:
            'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900',
    },
    rejected: {
        icon: CircleX,
        className:
            'bg-red-50 text-red-800 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900',
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
                'inline-flex w-fit items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                tone,
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-3.5 shrink-0" />
            {t(`payments.status.${status}`)}
        </span>
    );
}
