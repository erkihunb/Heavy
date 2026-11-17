import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Check,
    CircleAlert,
    ClipboardList,
    KeyRound,
    ShieldCheck,
    SlidersHorizontal,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import PageHeader from '@/shell/Components/PageHeader';
import PermissionPicker from '@/shell/Components/Roles/PermissionPicker';
import type { PermissionEntry } from '@/shell/Components/Roles/PermissionPicker';
import {
    Button,
    FieldError,
    FormField,
    Input,
    Select,
    badgeClassName,
    buttonClassName,
    cx,
} from '@/shell/Components/ui';
import UserAvatar from '@/shell/Components/UserAvatar';
import RolePicker from '@/shell/Components/Users/RolePicker';
import type { PickableRole } from '@/shell/Components/Users/RolePicker';
import {
    EmploymentFields,
    FormSection,
    IdentityFields,
    PreferenceFields,
    RecordList,
    UsernameField,
    fullName,
} from '@/shell/Components/Users/StaffFormSections';
import type {
    BranchOption,
    SectionState,
    StaffFormData,
    StaffFormOptions,
} from '@/shell/Components/Users/StaffFormSections';
import {
    AvatarPicker,
    PasswordField,
    internationalPhone,
} from '@/shell/Components/Users/StaffProfileFields';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

export type { BranchOption };

type CreateData = StaffFormData & {
    email: string;
    active: boolean;
    roles: string[];
    permissions: string[];
    password: string;
    password_confirmation: string;
    avatar: File | null;
};

type SectionId =
    'personal' | 'account' | 'employment' | 'access' | 'preferences' | 'record';

/** Which section a server error belongs to, by the field it names. */
const SECTION_OF: Record<string, SectionId> = {
    name: 'personal',
    first_name: 'personal',
    middle_name: 'personal',
    last_name: 'personal',
    gender: 'personal',
    phone: 'personal',
    national_id: 'personal',
    avatar: 'personal',
    email: 'account',
    username: 'account',
    password: 'account',
    password_confirmation: 'account',
    active: 'account',
    institution: 'employment',
    department: 'employment',
    job_title: 'employment',
    employee_number: 'employment',
    manager_id: 'employment',
    employment_status: 'employment',
    branch_id: 'employment',
    roles: 'access',
    permissions: 'access',
    locale: 'preferences',
    timezone: 'preferences',
    theme: 'preferences',
    notify_email: 'preferences',
};

const sectionOf = (field: string): SectionId =>
    SECTION_OF[field.split('.')[0]] ?? 'personal';

const domId = (id: SectionId) => `section-${id}`;

/** Fourteen characters with a capital, a small letter, a digit and a sign, none easily confused. */
function generatePassword(): string {
    const sets = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnpqrstuvwxyz',
        '23456789',
        '!@#$%*?-',
    ];
    const all = sets.join('');
    const random = new Uint32Array(14);
    crypto.getRandomValues(random);
    const chars = Array.from(random, (value, index) => {
        const set = index < sets.length ? sets[index] : all;

        return set[value % set.length];
    });

    // The four guaranteed kinds are spread through the password, not left in front.
    for (let index = chars.length - 1; index > 0; index--) {
        const swap = random[index] % (index + 1);
        [chars[index], chars[swap]] = [chars[swap], chars[index]];
    }

    return chars.join('');
}

/** Goes to a section: its first field in error when there is one, otherwise its top. */
function goToSection(id: SectionId): void {
    const section = document.getElementById(domId(id));
    if (!section) return;

    const invalid = section.querySelector<HTMLElement>('[aria-invalid="true"]');
    const reduce = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    (invalid ?? section).scrollIntoView({
        behavior: reduce ? 'auto' : 'smooth',
        block: invalid ? 'center' : 'start',
    });
    invalid?.focus({ preventScroll: true });
}

/**
 * A new staff account (EUISIS Users/Create). The form is long, so it is laid
 * out for finding one's place: each part is its own card, a rail beside it
 * lists the parts with what is ready and what needs fixing and follows the
 * scroll, the actions stay at the bottom of the window, and a failed save
 * opens a summary that leads to each field in error. With no password the
 * person is e-mailed a link to choose one; with one they must replace it at
 * first sign-in. Their branch scope starts from where they work.
 */
export default function CreateUser({
    roles,
    branches,
    permissions,
    urls,
    ...options
}: StaffFormOptions & {
    roles: PickableRole[];
    branches: BranchOption[];
    permissions: PermissionEntry[];
    urls: { index: string; store: string };
}) {
    const { t, locale } = useLocale();
    const viewer = (
        usePage().props.auth as { user?: { name?: string } } | undefined
    )?.user?.name;
    const form = useForm<CreateData>({
        first_name: '',
        middle_name: '',
        last_name: '',
        username: '',
        national_id: '',
        gender: '',
        phone: '',
        email: '',
        active: true,
        password: '',
        password_confirmation: '',
        institution: '',
        department: '',
        job_title: '',
        employee_number: '',
        manager_id: '',
        employment_status: '',
        branch_id: '',
        roles: [],
        permissions: [],
        locale: options.locales.some((item) => item.value === locale)
            ? locale
            : (options.locales[0]?.value ?? 'am'),
        timezone: options.defaultTimezone,
        theme: options.themes[0] ?? 'system',
        notify_email: true,
        avatar: null,
    });
    const { data, errors } = form;

    const [generated, setGenerated] = useState(false);
    const [current, setCurrent] = useState<SectionId>('personal');
    const summary = useRef<HTMLDivElement>(null);

    const name = fullName(data);
    const preview = useMemo(
        () => (data.avatar ? URL.createObjectURL(data.avatar) : null),
        [data.avatar],
    );
    useEffect(
        () => () => {
            if (preview) URL.revokeObjectURL(preview);
        },
        [preview],
    );

    const errorList = Object.entries(errors) as [string, string][];
    const errorsIn = (id: SectionId) =>
        errorList.filter(([field]) => sectionOf(field) === id).length;

    const employmentFilled = [
        data.institution,
        data.department,
        data.job_title,
        data.employee_number,
        data.manager_id,
        data.employment_status,
    ].some((value) => value.trim() !== '');

    const sections: {
        id: SectionId;
        icon: ReactNode;
        title: string;
        help: string;
        state: SectionState;
    }[] = [
        {
            id: 'personal',
            icon: <UserRound className="size-4.5" />,
            title: t('rbac.users.personalSection'),
            help: t('rbac.users.personalSectionHelp'),
            state:
                data.first_name.trim() !== '' && data.middle_name.trim() !== ''
                    ? 'ready'
                    : 'todo',
        },
        {
            id: 'account',
            icon: <KeyRound className="size-4.5" />,
            title: t('rbac.users.accountSection'),
            help: t('rbac.users.accountSectionHelp'),
            state:
                data.email.trim() !== '' &&
                data.password === data.password_confirmation
                    ? 'ready'
                    : 'todo',
        },
        {
            id: 'employment',
            icon: <BriefcaseBusiness className="size-4.5" />,
            title: t('rbac.users.employmentSection'),
            help: t('rbac.users.employmentSectionHelp'),
            state: employmentFilled ? 'ready' : 'optional',
        },
        {
            id: 'access',
            icon: <ShieldCheck className="size-4.5" />,
            title: t('rbac.users.accessSection'),
            help: t('rbac.users.accessSectionHelp'),
            state: data.roles.length > 0 ? 'ready' : 'todo',
        },
        {
            id: 'preferences',
            icon: <SlidersHorizontal className="size-4.5" />,
            title: t('rbac.users.preferencesSection'),
            help: t('rbac.users.preferencesSectionHelp'),
            state: 'ready',
        },
        {
            id: 'record',
            icon: <ClipboardList className="size-4.5" />,
            title: t('rbac.users.recordSection'),
            help: t('rbac.users.recordSectionHelp'),
            state: 'automatic',
        },
    ];
    const required = sections.filter(
        (item) =>
            item.id === 'personal' ||
            item.id === 'account' ||
            item.id === 'access',
    );
    const done = required.filter((item) => item.state === 'ready').length;
    const progress = t('rbac.users.create.progress')
        .replace(':done', String(done))
        .replace(':total', String(required.length));

    // The rail follows the scroll: the section crossing the upper part of the window is the current one.
    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.find((entry) => entry.isIntersecting);
                if (visible) {
                    setCurrent(
                        visible.target.id.replace('section-', '') as SectionId,
                    );
                }
            },
            { rootMargin: '-15% 0px -70% 0px' },
        );
        document
            .querySelectorAll('[id^="section-"]')
            .forEach((element) => observer.observe(element));

        return () => observer.disconnect();
    }, []);

    const selectedIds = roles
        .filter((role) => data.roles.includes(role.name))
        .map((role) => role.id);

    function toggle(id: number) {
        const role = roles.find((item) => item.id === id);
        if (!role) return;
        form.setData(
            'roles',
            data.roles.includes(role.name)
                ? data.roles.filter((item) => item !== role.name)
                : [...data.roles, role.name],
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            phone: internationalPhone(values.phone),
            active: values.active ? 1 : 0,
            notify_email: values.notify_email ? 1 : 0,
        }));
        form.post(urls.store, {
            preserveScroll: true,
            forceFormData: true,
            // The summary takes the focus, so the failure is seen and announced wherever the person was.
            onError: () =>
                requestAnimationFrame(() => {
                    summary.current?.scrollIntoView({ block: 'center' });
                    summary.current?.focus({ preventScroll: true });
                }),
        });
    }

    const section = {
        data,
        setData: (key: keyof StaffFormData, value: string | boolean) =>
            form.setData(key, value as never),
        errors,
    };
    const permissionError =
        errors.permissions ??
        errorList.find(([key]) => key.startsWith('permissions.'))?.[1];
    const total = sections.length;
    const meta = (id: SectionId) => {
        const index = sections.findIndex((item) => item.id === id);

        return {
            ...sections[index],
            id: domId(id),
            number: index + 1,
            total,
            errorCount: errorsIn(id),
        };
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    backHref={urls.index}
                    title={t('admin.users.create_title')}
                    description={t('admin.users.create_intro')}
                />
            }
        >
            <Head title={t('admin.users.create_title')} />

            <form
                onSubmit={submit}
                noValidate
                className="grid items-start gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]"
            >
                <aside className="top-20 hidden space-y-4 lg:sticky lg:block">
                    <div className="rounded-panel border border-(--app-border) bg-(--app-surface) p-4">
                        <p className="text-[0.6875rem] font-medium tracking-wide text-(--app-muted-foreground) uppercase">
                            {t('rbac.users.create.previewTitle')}
                        </p>
                        <div className="mt-3 flex items-center gap-3">
                            <UserAvatar
                                src={preview}
                                name={name === '' ? '?' : name}
                                size={48}
                                className="shrink-0 ring-1 ring-(--app-border)"
                            />
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-(--app-foreground)">
                                    {name === ''
                                        ? t('rbac.users.create.previewNoName')
                                        : name}
                                </p>
                                <p className="truncate text-xs text-(--app-muted-foreground)">
                                    {data.job_title.trim() !== ''
                                        ? data.job_title
                                        : data.email.trim() !== ''
                                          ? data.email
                                          : t(
                                                'rbac.users.create.previewNoEmail',
                                            )}
                                </p>
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            <span
                                className={badgeClassName(
                                    data.active ? 'success' : 'danger',
                                )}
                            >
                                {data.active
                                    ? t('rbac.users.active')
                                    : t('rbac.users.inactive')}
                            </span>
                            {data.roles.length === 0 ? (
                                <span className="text-xs text-(--app-muted-foreground)">
                                    {t('rbac.users.create.previewNoRoles')}
                                </span>
                            ) : (
                                roles
                                    .filter((role) =>
                                        data.roles.includes(role.name),
                                    )
                                    .map((role) => (
                                        <span
                                            key={role.id}
                                            className={badgeClassName(
                                                'neutral',
                                            )}
                                        >
                                            {role.label}
                                        </span>
                                    ))
                            )}
                        </div>
                    </div>

                    <nav
                        aria-label={t('rbac.users.create.nav')}
                        className="hidden rounded-panel border border-(--app-border) bg-(--app-surface) p-2 lg:block"
                    >
                        <ol className="space-y-0.5">
                            {sections.map((item, index) => {
                                const count = errorsIn(item.id);
                                const active = current === item.id;

                                return (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            aria-current={
                                                active ? 'step' : undefined
                                            }
                                            onClick={() => goToSection(item.id)}
                                            className={cx(
                                                'flex w-full items-center gap-2.5 rounded-control px-2.5 py-2 text-left text-sm transition-colors',
                                                active
                                                    ? 'bg-primary/10 font-medium text-(--app-foreground)'
                                                    : 'text-(--app-muted-foreground) hover:bg-(--app-surface-muted) hover:text-(--app-foreground)',
                                            )}
                                        >
                                            <span
                                                aria-hidden="true"
                                                className={cx(
                                                    'inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                                    count > 0
                                                        ? 'bg-destructive/15 text-danger-strong'
                                                        : item.state === 'ready'
                                                          ? 'bg-success/15 text-success-strong'
                                                          : 'bg-(--app-surface-muted) text-(--app-muted-foreground)',
                                                )}
                                            >
                                                {count > 0 ? (
                                                    <CircleAlert className="size-3.5" />
                                                ) : item.state === 'ready' ? (
                                                    <Check className="size-3.5" />
                                                ) : (
                                                    index + 1
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1 truncate">
                                                {item.title}
                                            </span>
                                            {count > 0 ? (
                                                <span className="text-xs font-medium text-danger-strong">
                                                    {count}
                                                </span>
                                            ) : (
                                                (item.state === 'optional' ||
                                                    item.state ===
                                                        'automatic') && (
                                                    <span className="text-[0.6875rem] text-(--app-muted-foreground)">
                                                        {t(
                                                            `rbac.users.create.${item.state}`,
                                                        )}
                                                    </span>
                                                )
                                            )}
                                        </button>
                                    </li>
                                );
                            })}
                        </ol>
                    </nav>
                    <p className="hidden px-1 text-xs text-(--app-muted-foreground) lg:block">
                        {t('rbac.users.create.requiredLegend')}
                    </p>
                </aside>

                <div className="min-w-0 space-y-5">
                    {errorList.length > 0 && (
                        <div
                            ref={summary}
                            role="alert"
                            tabIndex={-1}
                            aria-labelledby="create-user-errors"
                            className="scroll-mt-24 rounded-panel border border-destructive/60 bg-destructive/8 p-4 outline-hidden focus-visible:ring-2 focus-visible:ring-destructive/40"
                        >
                            <h2
                                id="create-user-errors"
                                className="flex items-center gap-2 text-sm font-semibold text-danger-strong"
                            >
                                <CircleAlert
                                    aria-hidden="true"
                                    className="size-4 shrink-0"
                                />
                                {t('rbac.users.create.errorTitle').replace(
                                    ':count',
                                    String(errorList.length),
                                )}
                            </h2>
                            <p className="mt-1 text-xs text-(--app-muted-foreground)">
                                {t('rbac.users.create.errorHelp')}
                            </p>
                            <ul className="mt-2 space-y-1">
                                {errorList.map(([field, message]) => (
                                    <li key={field}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                goToSection(sectionOf(field))
                                            }
                                            className="text-left text-sm text-(--app-foreground) underline decoration-destructive/50 underline-offset-2 hover:decoration-destructive"
                                        >
                                            {message}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <p className="text-xs text-(--app-muted-foreground) lg:hidden">
                        {t('rbac.users.create.requiredLegend')}
                    </p>

                    <FormSection {...meta('personal')}>
                        <div className="grid gap-6 @xl:grid-cols-[11rem_minmax(0,1fr)]">
                            <AvatarPicker
                                stacked
                                name={name}
                                file={data.avatar}
                                error={errors.avatar}
                                onChange={(file) =>
                                    form.setData('avatar', file)
                                }
                            />
                            <div className="@container min-w-0">
                                <IdentityFields {...section} />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection {...meta('account')}>
                        <div className="grid gap-4 @md:grid-cols-2">
                            <FormField
                                label={t('admin.users.fields.email')}
                                required
                                error={errors.email}
                            >
                                {({ id, describedBy, invalid }) => (
                                    <Input
                                        id={id}
                                        type="email"
                                        inputMode="email"
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        autoComplete="off"
                                        placeholder={t(
                                            'rbac.users.emailPlaceholder',
                                        )}
                                        value={data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <UsernameField {...section} />
                            <PasswordField
                                toggle
                                label={t('rbac.users.password')}
                                value={data.password}
                                onChange={(value) => {
                                    form.setData('password', value);
                                    setGenerated(false);
                                }}
                                onGenerate={() => {
                                    const password = generatePassword();
                                    form.setData((values) => ({
                                        ...values,
                                        password,
                                        password_confirmation: password,
                                    }));
                                    setGenerated(true);
                                }}
                                error={errors.password}
                                description={
                                    generated
                                        ? t('rbac.users.create.generatedHint')
                                        : t('rbac.users.passwordHelp')
                                }
                            />
                            <PasswordField
                                label={t('rbac.users.confirmPassword')}
                                required={data.password !== ''}
                                value={data.password_confirmation}
                                onChange={(value) =>
                                    form.setData('password_confirmation', value)
                                }
                                error={errors.password_confirmation}
                                placeholder={t(
                                    'rbac.users.confirmPasswordPlaceholder',
                                )}
                            />
                            <FormField
                                label={t('rbac.users.fields.accountStatus')}
                                error={errors.active}
                                description={
                                    data.active
                                        ? undefined
                                        : t('rbac.users.createInactiveHelp')
                                }
                            >
                                {({ id, describedBy, invalid }) => (
                                    <Select
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        value={data.active ? '1' : '0'}
                                        onChange={(event) =>
                                            form.setData(
                                                'active',
                                                event.target.value === '1',
                                            )
                                        }
                                    >
                                        <option value="1">
                                            {t('rbac.users.active')}
                                        </option>
                                        <option value="0">
                                            {t('rbac.users.inactive')}
                                        </option>
                                    </Select>
                                )}
                            </FormField>
                            <FormField label={t('rbac.users.fields.userId')}>
                                {({ id }) => (
                                    <Input
                                        id={id}
                                        disabled
                                        readOnly
                                        value={t('rbac.users.userIdAuto')}
                                    />
                                )}
                            </FormField>
                        </div>
                    </FormSection>

                    <FormSection {...meta('employment')}>
                        <EmploymentFields
                            {...section}
                            managers={options.managers}
                            employmentStatuses={options.employmentStatuses}
                            branches={branches}
                        />
                    </FormSection>

                    <FormSection {...meta('access')}>
                        <div>
                            <h3 className="text-sm font-medium text-(--app-foreground)">
                                {t('rbac.users.fields.roles')}
                                <span
                                    aria-hidden="true"
                                    className="ml-0.5 text-danger-strong"
                                >
                                    *
                                </span>
                            </h3>
                            <p className="mt-0.5 mb-3 text-xs text-(--app-muted-foreground)">
                                {t('rbac.users.rolesHelp')}
                            </p>
                            <RolePicker
                                roles={roles}
                                selected={selectedIds}
                                onToggle={toggle}
                                onClear={() => form.setData('roles', [])}
                                error={
                                    errors.roles ??
                                    errorList.find(([key]) =>
                                        key.startsWith('roles.'),
                                    )?.[1]
                                }
                            />
                        </div>
                        {permissions.length > 0 && (
                            <details
                                className="rounded-control border border-(--app-border)"
                                open={
                                    data.permissions.length > 0 ||
                                    Boolean(permissionError)
                                }
                            >
                                <summary className="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-(--app-foreground)">
                                    <span>
                                        {t('rbac.users.extraPermissions')}
                                        <span className="block text-xs font-normal text-(--app-muted-foreground)">
                                            {t(
                                                'rbac.users.extraPermissionsHelp',
                                            )}
                                        </span>
                                    </span>
                                    <span
                                        className={cx(
                                            badgeClassName('neutral'),
                                            'shrink-0',
                                        )}
                                    >
                                        {data.permissions.length > 0
                                            ? t(
                                                  'rbac.users.extraPermissionsCount',
                                              ).replace(
                                                  ':count',
                                                  String(
                                                      data.permissions.length,
                                                  ),
                                              )
                                            : t('rbac.users.create.optional')}
                                    </span>
                                </summary>
                                <div className="border-t border-(--app-border) p-4">
                                    <PermissionPicker
                                        permissions={permissions}
                                        selected={data.permissions}
                                        onChange={(names) =>
                                            form.setData('permissions', names)
                                        }
                                        disabled={false}
                                        t={t as (key: string) => string}
                                    />
                                    <FieldError className="mt-2">
                                        {permissionError}
                                    </FieldError>
                                </div>
                            </details>
                        )}
                    </FormSection>

                    <FormSection {...meta('preferences')}>
                        <PreferenceFields
                            {...section}
                            locales={options.locales}
                            timezones={options.timezones}
                            themes={options.themes}
                        />
                    </FormSection>

                    <FormSection {...meta('record')}>
                        <RecordList
                            items={[
                                {
                                    label: t(
                                        'rbac.users.fields.emailVerification',
                                    ),
                                    value: t(
                                        'rbac.users.record.verifiedOnCreate',
                                    ),
                                },
                                {
                                    label: t('rbac.users.fields.twoFactor'),
                                    value: t(
                                        'rbac.users.record.twoFactorAtFirstSignIn',
                                    ),
                                },
                                {
                                    label: t('rbac.users.fields.createdBy'),
                                    value: t('rbac.users.record.you').replace(
                                        ':name',
                                        viewer ?? '',
                                    ),
                                },
                                {
                                    label: t('rbac.users.fields.createdAt'),
                                    value: t('rbac.users.record.now'),
                                },
                            ]}
                        />
                    </FormSection>

                    <div className="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-panel border border-(--app-border-strong) bg-(--app-surface) px-4 py-3 shadow-lg">
                        <p
                            aria-live="polite"
                            className="flex items-center gap-2 text-xs text-(--app-muted-foreground)"
                        >
                            <span
                                aria-hidden="true"
                                className="h-1.5 w-20 overflow-hidden rounded-full bg-(--app-surface-muted)"
                            >
                                <span
                                    className="block h-full rounded-full bg-primary transition-[width] duration-200 motion-reduce:transition-none"
                                    style={{
                                        width: `${(done / required.length) * 100}%`,
                                    }}
                                />
                            </span>
                            {progress}
                        </p>
                        <div className="flex gap-2">
                            <Link
                                href={urls.index}
                                className={buttonClassName({
                                    variant: 'ghost',
                                })}
                            >
                                {t('shell.common.cancel')}
                            </Link>
                            <Button type="submit" loading={form.processing}>
                                {t('admin.users.create')}
                            </Button>
                        </div>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
