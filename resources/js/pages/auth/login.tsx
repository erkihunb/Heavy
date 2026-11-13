import { Form, Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { LoginHero } from '@/components/auth/login-hero/LoginHero';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
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
            // The way in, met first and used daily: the panel's own text is
            // simply there. The card has its own entrance below.
            still
            scene={<LoginHero />}
        >
            <Head title={t('auth_ui.login.submit')} />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <LoginFields
                        status={status}
                        canResetPassword={canResetPassword}
                        processing={processing}
                        errors={errors}
                    />
                )}
            </Form>
        </AuthLayout>
    );
}

type FieldsProps = {
    status?: string;
    canResetPassword: boolean;
    processing: boolean;
    errors: Partial<Record<string, string>>;
};

/**
 * The fields, plus the two things that react to what the server said: the
 * card shakes once when a sign-in is refused, and the fields arrive in
 * order the first time the page is drawn.
 */
function LoginFields({
    status,
    canResetPassword,
    processing,
    errors,
}: FieldsProps) {
    const { t } = useTranslation();
    const reduced = useReducedMotion();
    const shake = useErrorShake(errors, reduced);

    /* Each field joins a moment after the one above it, once. */
    const step = (index: number) =>
        reduced
            ? undefined
            : ({
                  animation: `hero-card-in 300ms ease-out ${index * 40}ms both`,
              } as const);

    return (
        <div ref={shake} className="grid gap-5">
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

            <div className="grid gap-2" style={step(0)}>
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
                    aria-describedby={errors.email ? 'email-error' : undefined}
                    aria-invalid={errors.email ? true : undefined}
                />
                <InputError id="email-error" message={errors.email} />
            </div>

            <div className="grid gap-2" style={step(1)}>
                <div className="flex items-center justify-between gap-3">
                    <Label htmlFor="password">{t('auth_ui.password')}</Label>
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
                        errors.password ? 'password-error' : undefined
                    }
                    aria-invalid={errors.password ? true : undefined}
                />
                <InputError id="password-error" message={errors.password} />
            </div>

            <div className="flex items-center gap-3" style={step(2)}>
                <Checkbox id="remember" name="remember" tabIndex={3} />
                <Label
                    htmlFor="remember"
                    className="font-normal text-muted-foreground"
                >
                    {t('auth_ui.login.remember')}
                </Label>
            </div>

            <div style={step(3)}>
                <Button
                    type="submit"
                    size="lg"
                    className="mt-1 w-full transition-[transform,box-shadow] duration-150 hover:-translate-y-px hover:shadow-raised motion-reduce:transform-none"
                    tabIndex={4}
                    disabled={processing}
                    data-test="login-button"
                >
                    {processing && <Spinner />}
                    {t('auth_ui.login.submit')}
                </Button>
            </div>
        </div>
    );
}

/**
 * One short shake when the server refuses a sign-in, and never a repeat:
 * the class is taken off as soon as it has played, and a new error has to
 * arrive before it goes on again.
 */
function useErrorShake(
    errors: Partial<Record<string, string>>,
    reduced: boolean,
) {
    const ref = useRef<HTMLDivElement>(null);
    const [token, setToken] = useState(0);
    const failed = Boolean(errors.email ?? errors.password);
    const wasFailed = useRef(false);

    useEffect(() => {
        if (failed && !wasFailed.current) {
            setToken((value) => value + 1);
        }

        wasFailed.current = failed;
    }, [failed]);

    useEffect(() => {
        const element = ref.current;

        if (!element || token === 0 || reduced) {
            return;
        }

        element.classList.add('hero-shake');
        const remove = () => element.classList.remove('hero-shake');

        element.addEventListener('animationend', remove, { once: true });

        return () => {
            element.removeEventListener('animationend', remove);
            remove();
        };
    }, [token, reduced]);

    return ref;
}
