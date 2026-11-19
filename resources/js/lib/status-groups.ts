import type { PermitStatus } from '@/types/enums';

/**
 * The seven groups people filter permits by, each named by its first status
 * (mirrors App\Enums\PermitStatus::groups()). A filter on a group finds
 * every status in it; the badges still name each status.
 */
export const PERMIT_STATUS_GROUPS = [
    'submitted',
    'changes_requested',
    'awaiting_payment',
    'payment_confirmed',
    'active',
    'rejected',
    'expired',
] as const satisfies readonly PermitStatus[];

export type PermitStatusGroup = (typeof PERMIT_STATUS_GROUPS)[number];
