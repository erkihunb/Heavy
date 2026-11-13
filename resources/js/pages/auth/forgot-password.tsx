import { Form, Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { useTranslation } from '@/lib/i18n';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth_ui.forgot.title')}
            description={t('auth_ui.forgot.description')}
        >
            <Head title={t('auth_ui.forgot.title')} />

            {/* "A link is on its way" is read before the field, not after the button. */}
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

            <Form {...email.form()} className="grid gap-5">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('auth_ui.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
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

                        <Button
                            type="submit"
                            size="lg"
                            className="mt-1 w-full"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            {t('auth_ui.forgot.submit')}
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            {t('auth_ui.forgot.back_prefix')}{' '}
                            <TextLink href={login()}>
                                {t('auth_ui.forgot.back_link')}
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
