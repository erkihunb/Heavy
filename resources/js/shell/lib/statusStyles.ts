/**
 * The single source of truth for status colouring.
 *
 * Previously this map spent eleven different hues — emerald, yellow, orange,
 * blue, sky, indigo, purple, red, rose, gray — on statuses whose differences
 * were not worth a colour. "Verified" in sky and "Approved" in indigo sit two
 * rows apart in a table and read as two unrelated kinds of thing, when both
 * simply mean *this went through*. That is the "rainbow dashboard" effect, and
 * it costs real legibility: when eleven colours are in play, none of them
 * carries a signal.
 *
 * There are now five tones, and a status earns a colour only by answering
 * "what should the operator DO about this?":
 *
 *   neutral  — nothing to do; a resting or historical state
 *   success  — the good terminal state; nothing to do
 *   info     — in flight, moving through a workflow on its own
 *   warning  — someone needs to act, but nothing is broken
 *   danger   — blocked, refused, or lost; needs attention now
 *
 * Colour is never the only carrier: every badge renders its label as text, so
 * the tone is redundant reinforcement rather than the message itself.
 */

export type StatusTone = 'neutral' | 'success' | 'info' | 'warning' | 'danger';

export type StatusStyle = {
    label: string;
    tone: StatusTone;
    className: string;
};

/*
 * Low-saturation fills with a hairline border, rather than the saturated
 * chips used before. At the density of an admin table — twenty badges in one
 * viewport — saturated fills fight the data for attention; a tinted surface
 * with a defined edge stays readable without shouting.
 */
export const toneClasses: Record<StatusTone, string> = {
    neutral:
        'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-200 ' +
        'dark:bg-slate-800/60 dark:text-slate-300 dark:ring-slate-700',
    success:
        'bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-200 ' +
        'dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900',
    info:
        'bg-primary-50 text-primary-800 ring-1 ring-inset ring-primary-200 ' +
        'dark:bg-primary-950 dark:text-primary-200 dark:ring-primary-800',
    warning:
        'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200 ' +
        'dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
    danger:
        'bg-red-50 text-red-800 ring-1 ring-inset ring-red-200 ' +
        'dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900',
};

/**
 * Status key → [English label, tone].
 *
 * The label is a fallback only. Callers that have a translated label pass it
 * via the `label` prop on StatusBadge; this map keeps the many call sites that
 * render a raw enum value from showing a bare snake_case string.
 */
const STATUS_MAP: Record<string, [label: string, tone: StatusTone]> = {
    /* ── Resting / historical ─────────────────────────────── */
    draft: ['Draft', 'neutral'],
    inactive: ['Inactive', 'neutral'],
    expired: ['Expired', 'neutral'],
    withdrawn: ['Withdrawn', 'neutral'],
    archived: ['Archived', 'neutral'],
    closed: ['Closed', 'neutral'],

    /* ── Good terminal state ──────────────────────────────── */
    active: ['Active', 'success'],
    approved: ['Approved', 'success'],
    issued: ['Issued', 'success'],
    verified: ['Verified', 'success'],
    paid: ['Paid', 'success'],
    completed: ['Completed', 'success'],

    /* ── In flight ────────────────────────────────────────── */
    submitted: ['Submitted', 'info'],
    under_review: ['Under review', 'info'],
    payment_submitted: ['Payment submitted', 'info'],
    payment_confirmed: ['Paid, waiting to be issued', 'info'],
    in_progress: ['In progress', 'info'],
    processing: ['Processing', 'info'],

    /* ── Someone needs to act ─────────────────────────────── */
    pending: ['Pending', 'warning'],
    changes_requested: ['Changes requested', 'warning'],
    awaiting_payment: ['Awaiting payment', 'warning'],
    overdue: ['Overdue', 'warning'],

    /* ── Blocked, refused or lost ─────────────────────────── */
    rejected: ['Rejected', 'danger'],
    revoked: ['Revoked', 'danger'],
    cancelled: ['Cancelled', 'danger'],
    suspended: ['Suspended', 'danger'],
    failed: ['Failed', 'danger'],
    blocked: ['Blocked', 'danger'],
    missing: ['Missing', 'danger'],
    error: ['Error', 'danger'],
};

const FALLBACK_TONE: StatusTone = 'neutral';

/**
 * Turns a raw status value into a label and a tone.
 *
 * Unrecognised values fall back to neutral rather than to a guess: an unknown
 * status is precisely the case where inventing a colour would mislead.
 */
export function getStatusStyle(status: string): StatusStyle {
    const normalized = (status ?? '').toLowerCase().replace(/[\s-]+/g, '_');
    const found = STATUS_MAP[normalized];

    if (!found) {
        return {
            label: status,
            tone: FALLBACK_TONE,
            className: toneClasses[FALLBACK_TONE],
        };
    }

    const [label, tone] = found;

    return { label: label || status, tone, className: toneClasses[tone] };
}
