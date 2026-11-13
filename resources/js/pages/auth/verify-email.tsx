import { Form, Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { useTranslation } from '@/lib/i18n';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <AuthLayout
            title={t('auth_ui.verify.title')}
            description={t('auth_ui.verify.description')}
        >
            <Head title={t('auth_ui.verify.title')} />

            {status === 'verification-link-sent' && (
                <p
                    role="status"
                    className="flex items-start gap-2 rounded-control border border-success/40 bg-success/10 px-3 py-2.5 text-sm font-medium text-success"
                >
                    <CircleCheck
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {t('auth_ui.verify.sent')}
                </p>
            )}

            <Form {...send.form()} className="grid gap-4">
                {({ processing }) => (
                    <>
                        <Button
                            type="submit"
                            size="lg"
                            variant="secondary"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('auth_ui.verify.resend')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('auth_ui.verify.logout')}
                        </TextLink>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
