import { usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import UserAvatar from '@/shell/Components/UserAvatar';

/**
 * Who the account belongs to, at the top of the person's own account pages:
 * a band in the brand colour, the avatar set into its lower edge, then the
 * name, the roles held and the e-mail address. Read-only; the tabs below
 * change these.
 */
export function ProfileSummary() {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const user = auth.user;

    return (
        <section
            aria-label={user.name}
            className="overflow-hidden rounded-card border border-border bg-card"
        >
            <div aria-hidden="true" className="h-24 bg-primary sm:h-28" />
            <div className="flex flex-col gap-4 px-5 pb-5 sm:flex-row sm:items-end sm:gap-5 sm:px-6">
                <UserAvatar
                    src={user.avatar}
                    name={user.name}
                    size={88}
                    className="-mt-11 shrink-0 ring-4 ring-card"
                />
                <div className="min-w-0 flex-1 sm:pb-1">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <h2 className="text-section-title break-words text-foreground">
                            {user.name}
                        </h2>
                        {user.roles.map((role) => (
                            <span
                                key={role}
                                className="rounded-badge bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand-strong"
                            >
                                {t(`roles.${role}.name`)}
                            </span>
                        ))}
                    </div>
                    <p className="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span className="inline-flex min-w-0 items-center gap-1.5">
                            <Mail
                                aria-hidden="true"
                                className="size-4 shrink-0"
                            />
                            <span className="break-all" translate="no">
                                {user.email}
                            </span>
                        </span>
                    </p>
                </div>
            </div>
        </section>
    );
}
