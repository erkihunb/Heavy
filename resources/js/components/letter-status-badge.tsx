import { CircleCheck } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { useTranslation } from '@/lib/i18n';
import type { PermitStatus } from '@/types/enums';
import { badgeClassName } from '@app/ui';

/**
 * A vehicle's status within its request letter. The letter is paid for once,
 * so a vehicle waiting for that payment reads "Approved"; the payment itself
 * is shown once for the whole letter.
 */
export function LetterStatusBadge({ status }: { status: PermitStatus }) {
    const { t } = useTranslation();

    if (status !== 'awaiting_payment') {
        return <StatusBadge status={status} />;
    }

    return (
        <span
            data-status={status}
            className={`w-fit ${badgeClassName('success')}`}
        >
            <CircleCheck aria-hidden="true" className="size-3.5 shrink-0" />
            {t('letters.table.approved')}
        </span>
    );
}
