import type { PermitStatus } from '@/types/enums';

/**
 * The colour of each permit status in a chart (the dashboard's and the
 * reports page's status rings), so a status reads the same wherever it is
 * drawn. Waiting is blue, money is amber, issued is green, ended is grey and
 * refused is red.
 */
export const PERMIT_STATUS_COLOR: Record<PermitStatus, string> = {
    draft: 'bg-muted-foreground/30',
    submitted: 'bg-primary/55',
    under_review: 'bg-primary',
    changes_requested: 'bg-warning/60',
    rejected: 'bg-destructive/70',
    awaiting_payment: 'bg-warning',
    payment_submitted: 'bg-info/60',
    payment_confirmed: 'bg-info',
    issued: 'bg-success/60',
    active: 'bg-success',
    expired: 'bg-muted-foreground/55',
    revoked: 'bg-destructive',
    withdrawn: 'bg-muted-foreground/20',
};
