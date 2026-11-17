import type { ReactNode } from 'react';
import {
    FormField,
    Input,
    Select,
    badgeClassName,
    cx,
} from '@/shell/Components/ui';
import {
    GenderField,
    NationalIdField,
    PhoneField,
} from '@/shell/Components/Users/StaffProfileFields';
import { useLocale } from '@/shell/hooks/useLocale';
import type { TranslationKey } from '@/types/translations';

export type LocaleOption = { value: string; label: string };

export type BranchOption = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

export type ManagerOption = {
    id: number;
    name: string;
    job_title: string | null;
};

/** What the create and edit forms choose from (UserController::formOptions()). */
export type StaffFormOptions = {
    locales: LocaleOption[];
    timezones: string[];
    defaultTimezone: string;
    employmentStatuses: string[];
    themes: string[];
    managers: ManagerOption[];
};

/** The part of a staff record both forms edit. */
export type StaffFormData = {
    first_name: string;
    middle_name: string;
    last_name: string;
    username: string;
    national_id: string;
    gender: string;
    phone: string;
    institution: string;
    department: string;
    job_title: string;
    employee_number: string;
    manager_id: string;
    employment_status: string;
    branch_id: string;
    locale: string;
    timezone: string;
    theme: string;
    notify_email: boolean;
};

export type StaffFormErrors = Partial<Record<keyof StaffFormData, string>>;

export type StaffFormSetter = (
    key: keyof StaffFormData,
    value: string | boolean,
) => void;

type SectionProps = {
    data: StaffFormData;
    setData: StaffFormSetter;
    errors: StaffFormErrors;
    disabled?: boolean;
};

/** The whole name as the parts make it, for the photo's initials and the page title. */
export function fullName(
    data: Pick<StaffFormData, 'first_name' | 'middle_name' | 'last_name'>,
): string {
    return [data.first_name, data.middle_name, data.last_name]
        .map((part) => part.trim())
        .filter(Boolean)
        .join(' ');
}

/** One numbered part of a form, under a rule from the one before. */
export function Step({
    number,
    title,
    help,
    children,
}: {
    number: number;
    title: string;
    help?: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-5 border-t border-(--app-border) pt-6 first:border-t-0 first:pt-0">
            <div>
                <h2 className="flex items-center gap-2 text-sm font-semibold text-(--app-foreground)">
                    <span className="inline-flex size-6 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary">
                        {number}
                    </span>
                    {title}
                </h2>
                {help && (
                    <p className="mt-1 text-xs text-(--app-muted-foreground)">
                        {help}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

function TextField({
    name,
    label,
    data,
    setData,
    errors,
    disabled,
    required,
    placeholder,
    description,
    maxLength,
    className,
}: SectionProps & {
    name: keyof StaffFormData;
    label: string;
    required?: boolean;
    placeholder?: string;
    description?: string;
    maxLength?: number;
    className?: string;
}) {
    return (
        <FormField
            label={label}
            required={required}
            error={errors[name]}
            description={description}
            className={className}
        >
            {({ id, describedBy, invalid }) => (
                <Input
                    id={id}
                    aria-describedby={describedBy}
                    aria-invalid={invalid}
                    disabled={disabled}
                    autoComplete="off"
                    maxLength={maxLength}
                    placeholder={placeholder}
                    value={String(data[name])}
                    onChange={(event) => setData(name, event.target.value)}
                />
            )}
        </FormField>
    );
}

/** The name in its three parts, national ID, gender and phone. */
export function IdentityFields(props: SectionProps) {
    const { t } = useLocale();
    const { data, setData, errors, disabled } = props;

    return (
        <div className="grid content-start gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            <TextField
                {...props}
                name="first_name"
                label={t('rbac.users.fields.firstName')}
                required
                maxLength={100}
            />
            <TextField
                {...props}
                name="middle_name"
                label={t('rbac.users.fields.middleName')}
                required
                maxLength={100}
            />
            <TextField
                {...props}
                name="last_name"
                label={t('rbac.users.fields.lastName')}
                maxLength={100}
            />
            <GenderField
                value={data.gender}
                onChange={(value) => setData('gender', value)}
                error={errors.gender}
                disabled={disabled}
            />
            <PhoneField
                value={data.phone}
                onChange={(value) => setData('phone', value)}
                error={errors.phone}
                disabled={disabled}
            />
            <NationalIdField
                value={data.national_id}
                onChange={(value) => setData('national_id', value)}
                error={errors.national_id}
                disabled={disabled}
            />
        </div>
    );
}

export function UsernameField(props: SectionProps) {
    const { t } = useLocale();

    return (
        <TextField
            {...props}
            name="username"
            label={t('rbac.users.fields.username')}
            placeholder={t('rbac.users.usernamePlaceholder')}
            description={t('rbac.users.usernameHelp')}
            maxLength={40}
        />
    );
}

/** Institution, department, job title, staff number, manager, employment status and branch. */
export function EmploymentFields({
    managers,
    employmentStatuses,
    branches,
    ...props
}: SectionProps & {
    managers: ManagerOption[];
    employmentStatuses: string[];
    branches: BranchOption[];
}) {
    const { t } = useLocale();
    const { data, setData, errors, disabled } = props;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <TextField
                {...props}
                name="institution"
                label={t('rbac.users.fields.institution')}
                placeholder={t('rbac.users.institutionPlaceholder')}
                maxLength={160}
            />
            <TextField
                {...props}
                name="department"
                label={t('rbac.users.fields.department')}
                placeholder={t('rbac.users.departmentPlaceholder')}
                maxLength={160}
            />
            <TextField
                {...props}
                name="job_title"
                label={t('rbac.users.fields.jobTitle')}
                placeholder={t('rbac.users.jobTitlePlaceholder')}
                maxLength={120}
            />
            <TextField
                {...props}
                name="employee_number"
                label={t('rbac.users.fields.employeeNumber')}
                description={t('rbac.users.employeeNumberHelp')}
                maxLength={40}
            />
            <FormField
                label={t('rbac.users.fields.manager')}
                error={errors.manager_id}
            >
                {({ id, describedBy, invalid }) => (
                    <Select
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        disabled={disabled}
                        value={data.manager_id}
                        onChange={(event) =>
                            setData('manager_id', event.target.value)
                        }
                    >
                        <option value="">{t('rbac.users.managerNone')}</option>
                        {managers.map((manager) => (
                            <option key={manager.id} value={String(manager.id)}>
                                {manager.job_title
                                    ? `${manager.name} · ${manager.job_title}`
                                    : manager.name}
                            </option>
                        ))}
                    </Select>
                )}
            </FormField>
            <FormField
                label={t('rbac.users.fields.employmentStatus')}
                error={errors.employment_status}
            >
                {({ id, describedBy, invalid }) => (
                    <Select
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        disabled={disabled}
                        value={data.employment_status}
                        onChange={(event) =>
                            setData('employment_status', event.target.value)
                        }
                    >
                        <option value="">
                            {t('rbac.users.employmentStatusPlaceholder')}
                        </option>
                        {employmentStatuses.map((status) => (
                            <option key={status} value={status}>
                                {t(
                                    `rbac.users.employmentStatuses.${status}` as TranslationKey,
                                )}
                            </option>
                        ))}
                    </Select>
                )}
            </FormField>
            <FormField
                label={t('rbac.users.worksAt')}
                error={errors.branch_id}
                description={t('rbac.users.worksAtHelp')}
                className="sm:col-span-2"
            >
                {({ id, describedBy, invalid }) => (
                    <Select
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        disabled={disabled}
                        value={data.branch_id}
                        onChange={(event) =>
                            setData('branch_id', event.target.value)
                        }
                    >
                        <option value="">{t('rbac.scopes.headOffice')}</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={String(branch.id)}>
                                {branch.code} · {branch.name}
                                {branch.is_active
                                    ? ''
                                    : ` (${t('admin.users.branch_closed')})`}
                            </option>
                        ))}
                    </Select>
                )}
            </FormField>
        </div>
    );
}

/** Language, time zone, theme and the application e-mails. */
export function PreferenceFields({
    locales,
    timezones,
    themes,
    ...props
}: SectionProps & {
    locales: LocaleOption[];
    timezones: string[];
    themes: string[];
}) {
    const { t } = useLocale();
    const { data, setData, errors, disabled } = props;
    // The stored zone stays choosable even when it is not one of the offered ones.
    const zones =
        data.timezone === '' || timezones.includes(data.timezone)
            ? timezones
            : [data.timezone, ...timezones];

    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <FormField
                label={t('rbac.users.fields.locale')}
                error={errors.locale}
                description={t('rbac.users.localeHelp')}
            >
                {({ id, describedBy, invalid }) => (
                    <Select
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        disabled={disabled}
                        value={data.locale}
                        onChange={(event) =>
                            setData('locale', event.target.value)
                        }
                    >
                        {locales.map((locale) => (
                            <option key={locale.value} value={locale.value}>
                                {locale.label}
                            </option>
                        ))}
                    </Select>
                )}
            </FormField>
            <FormField
                label={t('rbac.users.fields.timezone')}
                error={errors.timezone}
                description={t('rbac.users.timezoneHelp')}
            >
                {({ id, describedBy, invalid }) => (
                    <Select
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        disabled={disabled}
                        value={data.timezone}
                        onChange={(event) =>
                            setData('timezone', event.target.value)
                        }
                    >
                        {zones.map((zone) => (
                            <option key={zone} value={zone}>
                                {zone.replace(/_/g, ' ')}
                            </option>
                        ))}
                    </Select>
                )}
            </FormField>
            <FormField
                label={t('rbac.users.fields.theme')}
                error={errors.theme}
                description={t('rbac.users.themeHelp')}
            >
                {({ id, describedBy, invalid }) => (
                    <Select
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        disabled={disabled}
                        value={data.theme}
                        onChange={(event) =>
                            setData('theme', event.target.value)
                        }
                    >
                        {themes.map((theme) => (
                            <option key={theme} value={theme}>
                                {t(
                                    `rbac.users.themes.${theme}` as TranslationKey,
                                )}
                            </option>
                        ))}
                    </Select>
                )}
            </FormField>
            <fieldset className="sm:col-span-3">
                <legend className="text-sm font-medium text-(--app-foreground)">
                    {t('rbac.users.fields.notifications')}
                </legend>
                <label className="mt-2 flex items-start gap-2.5 text-sm text-(--app-foreground)">
                    <input
                        type="checkbox"
                        className="mt-0.5 size-4 rounded border-(--app-border-strong) accent-primary"
                        disabled={disabled}
                        checked={data.notify_email}
                        onChange={(event) =>
                            setData('notify_email', event.target.checked)
                        }
                    />
                    <span>
                        {t('rbac.users.notifyEmail')}
                        <span className="block text-xs text-(--app-muted-foreground)">
                            {t('rbac.users.notifyEmailHelp')}
                        </span>
                    </span>
                </label>
            </fieldset>
        </div>
    );
}

/** What the system records about an account: label and value pairs, read only. */
export function RecordList({
    items,
}: {
    items: { label: string; value: ReactNode }[];
}) {
    return (
        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
            {items.map((item) => (
                <div key={item.label} className="min-w-0">
                    <dt className="text-xs text-(--app-muted-foreground)">
                        {item.label}
                    </dt>
                    <dd className="mt-0.5 text-sm font-medium break-words text-(--app-foreground)">
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export type SectionState = 'todo' | 'ready' | 'optional' | 'automatic';

/**
 * One part of a long form as its own card: an icon and title, what it is for,
 * and on the right whether it is ready, optional, filled in by the system or
 * has fields to fix. The id is what the section rail and the error summary
 * jump to.
 */
export function FormSection({
    id,
    number,
    total,
    icon,
    title,
    help,
    state,
    errorCount = 0,
    children,
}: {
    id: string;
    number: number;
    total: number;
    icon: ReactNode;
    title: string;
    help: string;
    state: SectionState;
    errorCount?: number;
    children: ReactNode;
}) {
    const { t } = useLocale();
    const headingId = `${id}-title`;

    return (
        <section
            id={id}
            aria-labelledby={headingId}
            className={cx(
                'scroll-mt-24 rounded-panel border bg-(--app-surface)',
                errorCount > 0
                    ? 'border-destructive/60'
                    : 'border-(--app-border)',
            )}
        >
            <header className="flex items-start gap-3 border-b border-(--app-border) px-5 py-4 sm:px-6">
                <span
                    aria-hidden="true"
                    className="inline-flex size-9 shrink-0 items-center justify-center rounded-control bg-primary/12 text-primary"
                >
                    {icon}
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-[0.6875rem] font-medium tracking-wide text-(--app-muted-foreground) uppercase">
                        {number} / {total}
                    </p>
                    <h2
                        id={headingId}
                        className="text-base font-semibold text-(--app-foreground)"
                    >
                        {title}
                    </h2>
                    <p className="mt-0.5 text-xs text-(--app-muted-foreground)">
                        {help}
                    </p>
                </div>
                <SectionBadge state={state} errorCount={errorCount} t={t} />
            </header>
            <div className="space-y-5 px-5 py-5 sm:px-6">{children}</div>
        </section>
    );
}

export function SectionBadge({
    state,
    errorCount,
    t,
}: {
    state: SectionState;
    errorCount: number;
    t: (key: TranslationKey) => string;
}) {
    if (errorCount > 0) {
        return (
            <span className={cx(badgeClassName('danger'), 'shrink-0')}>
                {t('rbac.users.create.needsAttention').replace(
                    ':count',
                    String(errorCount),
                )}
            </span>
        );
    }

    if (state === 'todo') return null;

    return (
        <span
            className={cx(
                badgeClassName(state === 'ready' ? 'success' : 'neutral'),
                'shrink-0',
            )}
        >
            {t(`rbac.users.create.${state}`)}
        </span>
    );
}
