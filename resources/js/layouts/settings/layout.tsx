import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/page-header';
import { PageTabs } from '@/components/page-tabs';
import { ProfileSummary } from '@/components/profile-summary';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';

/**
 * A person's own account: the heading, a summary of who is signed in,
 * underlined tabs for account, password and two-factor authentication, then
 * the section cards of the open tab. Light or dark is chosen in the top bar; the
 * look of the system is the Admin's (System settings → Appearance).
 */
export default function SettingsLayout({
    children,
    wide = false,
}: PropsWithChildren<{
    /** The open tab uses the whole page width (a long form) instead of a reading column. */
    wide?: boolean;
}>) {
    const { t } = useTranslation();

    return (
        <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <PageHeader
                title={t('settings.title')}
                description={t('settings.intro')}
            />
            <ProfileSummary />
            <PageTabs
                label={t('settings.title')}
                tabs={[
                    { label: t('settings.nav.profile'), href: edit() },
                    { label: t('settings.nav.password'), href: editPassword() },
                    { label: t('settings.nav.two_factor'), href: show() },
                ]}
            />
            <div
                className={cn(
                    'flex flex-col gap-6',
                    wide ? 'w-full' : 'max-w-3xl',
                )}
            >
                {children}
            </div>
        </div>
    );
}
