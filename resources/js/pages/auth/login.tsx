import { Form, Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { useTranslation } from '@/lib/i18n';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth_ui.login.title')}
            description={t('auth_ui.login.description')}
        >
            <Head title={t('auth_ui.login.submit')} />

            {/* A message such as "your password was reset" belongs above the
                form, where it is read before the fields, not after the button. */}
            {status && (
                <p
                    role="status"
                    className="flex items-start gap-2 rounded-control border border-success/40 bg-success/10 px-3 py-2.5 text-sm font-medium text-success"
                >
                    <CircleCheck
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {status}
                </p>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('auth_ui.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="email@example.com"
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
                            <div className="flex items-center justify-between gap-3">
                                <Label htmlFor="password">
                                    {t('auth_ui.password')}
                                </Label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-sm"
                                        tabIndex={5}
                                    >
                                        {t('auth_ui.login.forgot_password')}
                                    </TextLink>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder={t('auth_ui.password')}
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

                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            <Label
                                htmlFor="remember"
                                className="font-normal text-muted-foreground"
                            >
                                {t('auth_ui.login.remember')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="mt-1 w-full"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {t('auth_ui.login.submit')}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
