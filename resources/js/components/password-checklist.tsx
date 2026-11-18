import { usePage } from '@inertiajs/react';
import { Check, Circle, Info } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';

/** Mirrors App\Security\PasswordPolicy::summary(), shared on every page. */
export type PasswordPolicySummary = {
    min: number;
    max: number;
    mixed_case: boolean;
    numbers: boolean;
    symbols: boolean;
    history: number;
    personal: boolean;
    common: boolean;
    breached: boolean;
};

/**
 * What a new password must be, checked as it is typed (System settings ›
 * Security), as in EUISIS. Rules the browser cannot check — common, personal,
 * reused or breached passwords — are listed for information; the server
 * checks them when the form is sent.
 */
export function PasswordChecklist({
    value,
    id,
}: {
    value: string;
    id?: string;
}) {
    const { t } = useTranslation();
    const policy = (
        usePage().props as { passwordPolicy?: PasswordPolicySummary }
    ).passwordPolicy;

    if (!policy) {
        return null;
    }

    const length = [...value].length;
    const checks: { key: string; label: string; met: boolean }[] = [
        {
            key: 'min',
            label: t('auth_ui.password_checklist.min', { min: policy.min }),
            met: length >= policy.min,
        },
        ...(policy.max > 0
            ? [
                  {
                      key: 'max',
                      label: t('auth_ui.password_checklist.max', {
                          max: policy.max,
                      }),
                      met: length > 0 && length <= policy.max,
                  },
              ]
            : []),
        ...(policy.mixed_case
            ? [
                  {
                      key: 'mixed_case',
                      label: t('auth_ui.password_checklist.mixed_case'),
                      met: /\p{Lu}/u.test(value) && /\p{Ll}/u.test(value),
                  },
              ]
            : []),
        ...(policy.numbers
            ? [
                  {
                      key: 'numbers',
                      label: t('auth_ui.password_checklist.numbers'),
                      met: /\p{N}/u.test(value),
                  },
              ]
            : []),
        ...(policy.symbols
            ? [
                  {
                      key: 'symbols',
                      label: t('auth_ui.password_checklist.symbols'),
                      met: /[^\p{L}\p{N}\s]/u.test(value),
                  },
              ]
            : []),
    ];

    const notes = [
        policy.common && t('auth_ui.password_checklist.common'),
        policy.personal && t('auth_ui.password_checklist.personal'),
        policy.history > 0 &&
            t('auth_ui.password_checklist.history', { count: policy.history }),
        policy.breached && t('auth_ui.password_checklist.breached'),
    ].filter((note): note is string => typeof note === 'string');

    return (
        <div id={id} className="space-y-1.5 text-xs" aria-live="polite">
            <ul className="space-y-1">
                {checks.map((check) => (
                    <li
                        key={check.key}
                        className={
                            check.met
                                ? 'flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400'
                                : 'flex items-center gap-1.5 text-muted-foreground'
                        }
                    >
                        {check.met ? (
                            <Check aria-hidden className="size-3.5 shrink-0" />
                        ) : (
                            <Circle aria-hidden className="size-3 shrink-0" />
                        )}
                        <span>
                            {check.label}
                            <span className="sr-only">
                                {' '}
                                {check.met
                                    ? t('auth_ui.password_checklist.met')
                                    : t('auth_ui.password_checklist.not_met')}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
            {notes.length > 0 && (
                <ul className="space-y-1 text-muted-foreground">
                    {notes.map((note) => (
                        <li key={note} className="flex items-start gap-1.5">
                            <Info
                                aria-hidden
                                className="mt-0.5 size-3.5 shrink-0"
                            />
                            <span>{note}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
