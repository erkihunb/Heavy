import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import AuthLayout from '@/layouts/auth-layout';
import { useTranslation } from '@/lib/i18n';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const { t } = useTranslation();
    const [showRecoveryInput, setShowRecoveryInput] = useState(false);
    const [code, setCode] = useState('');

    // The two ways in: a code from the authenticator app, or a saved recovery code.
    const content = showRecoveryInput
        ? {
              title: t('auth_ui.two_factor.recovery_title'),
              description: t('auth_ui.two_factor.recovery_description'),
              toggle: t('auth_ui.two_factor.use_code'),
          }
        : {
              title: t('auth_ui.two_factor.title'),
              description: t('auth_ui.two_factor.description'),
              toggle: t('auth_ui.two_factor.use_recovery'),
          };

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <AuthLayout title={content.title} description={content.description}>
            <Head title={t('auth_ui.two_factor.title')} />

            <Form
                {...store.form()}
                className="grid gap-5"
                resetOnError
                resetOnSuccess={!showRecoveryInput}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {showRecoveryInput ? (
                            <div className="grid gap-2">
                                <Input
                                    name="recovery_code"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="one-time-code"
                                    placeholder={t(
                                        'auth_ui.two_factor.recovery_placeholder',
                                    )}
                                    aria-label={t(
                                        'auth_ui.two_factor.recovery_title',
                                    )}
                                    aria-describedby={
                                        errors.recovery_code
                                            ? 'recovery-code-error'
                                            : undefined
                                    }
                                    aria-invalid={
                                        errors.recovery_code ? true : undefined
                                    }
                                />
                                <InputError
                                    id="recovery-code-error"
                                    message={errors.recovery_code}
                                />
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-3 text-center">
                                <InputOTP
                                    name="code"
                                    maxLength={OTP_MAX_LENGTH}
                                    value={code}
                                    onChange={setCode}
                                    disabled={processing}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    aria-label={t('auth_ui.two_factor.title')}
                                    aria-describedby={
                                        errors.code ? 'code-error' : undefined
                                    }
                                    aria-invalid={
                                        errors.code ? true : undefined
                                    }
                                >
                                    <InputOTPGroup>
                                        {Array.from(
                                            { length: OTP_MAX_LENGTH },
                                            (_, index) => (
                                                <InputOTPSlot
                                                    key={index}
                                                    index={index}
                                                />
                                            ),
                                        )}
                                    </InputOTPGroup>
                                </InputOTP>
                                <InputError
                                    id="code-error"
                                    message={errors.code}
                                />
                            </div>
                        )}

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('auth_ui.two_factor.submit')}
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            {t('auth_ui.two_factor.or')}{' '}
                            <button
                                type="button"
                                className="cursor-pointer rounded-xs font-semibold text-foreground underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                onClick={() => toggleRecoveryMode(clearErrors)}
                            >
                                {content.toggle}
                            </button>
                        </p>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
