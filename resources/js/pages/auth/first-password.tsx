import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { useTranslation } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/first-password';

type Props = {
    email: string;
};

/**
 * Where signing in with the default password stops: the person is not signed
 * in yet, and chooses their own password before they can be.
 */
export default function FirstPassword({ email }: Props) {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth_ui.first_password.title')}
            description={t('auth_ui.first_password.description')}
        >
            <Head title={t('auth_ui.first_password.title')} />

            <Form
                {...store.form()}
                resetOnError={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-5">
                        {/* Shown so the person sees which account this is, never edited here. */}
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('auth_ui.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="username"
                                value={email}
                                readOnly
                                className="bg-muted text-muted-foreground"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('auth_ui.reset.new_password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder={t('auth_ui.reset.new_password')}
                                aria-describedby={
                                    errors.password
                                        ? 'password-error'
                                        : undefined
                                }
                                aria-invalid={
                                    errors.password ? true : undefined
                                }
                            />
                            <InputError
                                id="password-error"
                                message={errors.password}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('auth_ui.reset.confirm_password')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder={t(
                                    'auth_ui.reset.confirm_password',
                                )}
                            />
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="mt-1 w-full"
                            disabled={processing}
                            data-test="first-password-button"
                        >
                            {processing && <Spinner />}
                            {t('auth_ui.first_password.submit')}
                        </Button>

                        <div className="text-center text-sm">
                            <TextLink href={login()}>
                                {t('auth_ui.first_password.back')}
                            </TextLink>
                        </div>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
