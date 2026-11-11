import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/lib/i18n';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    showRole = false,
}: {
    user: User;
    showEmail?: boolean;
    showRole?: boolean;
}) {
    const getInitials = useInitials();
    const { t } = useTranslation();

    return (
        <>
            <Avatar className="size-[30px] overflow-hidden rounded-full ring-1 ring-border">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-full bg-muted text-xs font-semibold text-foreground">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold">{user.name}</span>
                {showRole && (
                    <span className="mt-0.5 truncate text-[11px] text-muted-foreground">
                        {t(`roles.${user.roles[0] ?? 'none'}.name`)}
                    </span>
                )}
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
