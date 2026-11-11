import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import { useRef } from 'react';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { useTranslation } from '@/lib/i18n';
import { edit } from '@/routes/user-password';
import type { BreadcrumbItem } from '@/types';

export default function Password() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('settings.password.title'), href: edit() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.password.title')} />

            <SettingsLayout>
                <SectionCard
                    id="password-title"
                    icon={LockKeyhole}
                    title={t('settings.password.heading')}
                    description={t('settings.password.description')}
                >
                    <div className="space-y-6">
                        <Form
                            {...PasswordController.update.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            resetOnError={[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]}
                            resetOnSuccess
                            onError={(errors) => {
                                if (errors.password) {
                                    passwordInput.current?.focus();
                                }

                                if (errors.current_password) {
                                    currentPasswordInput.current?.focus();
                                }
                            }}
                            className="space-y-6"
                        >
                            {({ errors, processing, recentlySuccessful }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="current_password">
                                            {t('settings.password.current')}
                                        </Label>

                                        <PasswordInput
                                            id="current_password"
                                            ref={currentPasswordInput}
                                            name="current_password"
                                            className="mt-1 block w-full"
                                            autoComplete="current-password"
                                            placeholder={t(
                                                'settings.password.current',
                                            )}
                                        />

                                        <InputError
                                            message={errors.current_password}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            {t('settings.password.new')}
                                        </Label>

                                        <PasswordInput
                                            id="password"
                                            ref={passwordInput}
                                            name="password"
                                            className="mt-1 block w-full"
                                            autoComplete="new-password"
                                            placeholder={t(
                                                'settings.password.new',
                                            )}
                                        />

                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            {t('settings.password.confirm')}
                                        </Label>

                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            className="mt-1 block w-full"
                                            autoComplete="new-password"
                                            placeholder={t(
                                                'settings.password.confirm',
                                            )}
                                        />

                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button
                                            disabled={processing}
                                            data-test="update-password-button"
                                        >
                                            {t('settings.password.save')}
                                        </Button>

                                        <Transition
                                            show={recentlySuccessful}
                                            enter="transition ease-in-out"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out"
                                            leaveTo="opacity-0"
                                        >
                                            <p className="text-sm text-muted-foreground">
                                                {t('settings.saved')}
                                            </p>
                                        </Transition>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </SectionCard>
            </SettingsLayout>
        </AppLayout>
    );
}
