import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useTranslation } from '@/lib/i18n';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { t } = useTranslation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="border-b border-border bg-muted px-4 py-3.5 font-normal">
                <p className="mb-1 text-eyebrow text-muted-foreground">
                    {t('nav.signed_in_as')}
                </p>
                <div className="flex items-center gap-2.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
                {user.roles.length > 0 && (
                    <div className="mt-2.5 flex flex-wrap gap-1.5">
                        {user.roles.map((role) => (
                            <span
                                key={role}
                                className="rounded-md border border-border bg-card px-2 py-0.5 text-xs text-muted-foreground"
                            >
                                {t(`roles.${role}.name`)}
                            </span>
                        ))}
                    </div>
                )}
            </DropdownMenuLabel>
            <DropdownMenuGroup className="p-1.5">
                <DropdownMenuItem asChild>
                    <Link
                        className="flex min-h-10 w-full cursor-pointer items-center"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        {t('nav.settings')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator className="my-0" />
            <DropdownMenuGroup className="p-1.5">
                <DropdownMenuItem asChild>
                    <Link
                        className="flex min-h-10 w-full cursor-pointer items-center text-red-700 focus:bg-red-50 focus:text-red-700 dark:text-red-400 dark:focus:bg-red-950/40"
                        href={logout()}
                        as="button"
                        onClick={handleLogout}
                        data-test="logout-button"
                    >
                        <LogOut className="mr-2" />
                        {t('nav.log_out')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
        </>
    );
}
