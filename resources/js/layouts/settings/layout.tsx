import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/page-header';
import { PageTabs } from '@/components/page-tabs';
import { useTranslation } from '@/lib/i18n';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';

/**
 * A person's own account (EUISIS profile page): the heading band, underlined
 * tabs for account, password and two-factor authentication, then the
 * section cards of the open tab. Light or dark is chosen in the top bar; the
 * look of the system is the Admin's (System settings → Appearance).
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useTranslation();

    return (
        <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <PageHeader
                title={t('settings.title')}
                description={t('settings.intro')}
            />
            <PageTabs
                label={t('settings.title')}
                tabs={[
                    { label: t('settings.nav.profile'), href: edit() },
                    { label: t('settings.nav.password'), href: editPassword() },
                    { label: t('settings.nav.two_factor'), href: show() },
                ]}
            />
            <div className="flex max-w-3xl flex-col gap-6">{children}</div>
        </div>
    );
}
