import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { useTranslation } from '@/lib/i18n';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth_ui.reset.title')}
            description={t('auth_ui.reset.description')}
        >
            <Head title={t('auth_ui.reset.title')} />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-5">
                        {/* The address the link was sent to: shown so the person
                            sees which account this is, never edited here. */}
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('auth_ui.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                readOnly
                                className="bg-muted text-muted-foreground"
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                                aria-invalid={errors.email ? true : undefined}
                            />
                            <InputError
                                id="email-error"
                                message={errors.email}
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
                                aria-describedby={
                                    errors.password_confirmation
                                        ? 'password-confirmation-error'
                                        : undefined
                                }
                                aria-invalid={
                                    errors.password_confirmation
                                        ? true
                                        : undefined
                                }
                            />
                            <InputError
                                id="password-confirmation-error"
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="mt-1 w-full"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {t('auth_ui.reset.submit')}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
