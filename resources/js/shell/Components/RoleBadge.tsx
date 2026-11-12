import { useLocale } from '@/shell/hooks/useLocale';

interface Props {
    /** Role code (App\Enums\Role): applicant, reviewer, approver, cashier, traffic_officer, registrar, admin. */
    role: string;
    /** Label to show; defaults to the role's name in lang/{en,am}/roles.php. */
    label?: string;
    className?: string;
}

/* One tint per kind of work, as in the source kit; an unknown role is neutral. */
const roleStyles: Record<string, string> = {
    admin: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    approver:
        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    reviewer: 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
    cashier:
        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    registrar:
        'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
    traffic_officer:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
};

const defaultStyle = 'bg-(--app-surface-muted) text-(--app-foreground)';

/** A person's role as a small tinted pill (RoleBadge). */
export default function RoleBadge({ role, label, className = '' }: Props) {
    const { t } = useLocale();
    const style = roleStyles[role] ?? defaultStyle;
    const key = `roles.${role}.name`;
    const translated = t(key);
    const text = label ?? (translated !== key ? translated : role);

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${style} ${className}`}
        >
            {text}
        </span>
    );
}
