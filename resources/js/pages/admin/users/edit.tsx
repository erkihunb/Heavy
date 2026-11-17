import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import PageHeader from '@/shell/Components/PageHeader';
import PermissionPicker from '@/shell/Components/Roles/PermissionPicker';
import type { PermissionEntry } from '@/shell/Components/Roles/PermissionPicker';
import {
    Alert,
    Button,
    FieldError,
    FormField,
    Input,
    badgeClassName,
} from '@/shell/Components/ui';
import type { PickableBranch } from '@/shell/Components/Users/BranchPicker';
import BranchScopesCard from '@/shell/Components/Users/BranchScopesCard';
import type { BranchScopeRow } from '@/shell/Components/Users/BranchScopesCard';
import RolePicker from '@/shell/Components/Users/RolePicker';
import type { PickableRole } from '@/shell/Components/Users/RolePicker';
import {
    EmploymentFields,
    IdentityFields,
    PreferenceFields,
    RecordList,
    Step,
    UsernameField,
    fullName,
} from '@/shell/Components/Users/StaffFormSections';
import type {
    BranchOption,
    StaffFormData,
    StaffFormOptions,
} from '@/shell/Components/Users/StaffFormSections';
import {
    AvatarPicker,
    internationalPhone,
    localPhone,
} from '@/shell/Components/Users/StaffProfileFields';
import { useConfirm } from '@/shell/hooks/useConfirm';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import type { UserRow } from './index';

type RecordDate = { ethiopian_label: string; gregorian: string } | null;

type EditableUser = UserRow & {
    branch_id: number | null;
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    username: string | null;
    phone: string | null;
    national_id: string | null;
    gender: string | null;
    institution: string | null;
    department: string | null;
    job_title: string | null;
    employee_number: string | null;
    manager_id: number | null;
    employment_status: string | null;
    locale: string;
    timezone: string | null;
    theme: string | null;
    notify_email: boolean;
    permission_names: string[];
    record: {
        email_verified_at: RecordDate;
        two_factor: boolean;
        last_login_at: RecordDate;
        last_login_time: string | null;
        last_login_ip: string | null;
        created_by: string | null;
        created_at: RecordDate;
        updated_at: RecordDate;
    };
    role_names: string[];
    is_applicant: boolean;
    deactivated_at: { ethiopian_label: string; gregorian: string } | null;
    deactivated_by: string | null;
    sees_everywhere: boolean;
};

/**
 * One user (EUISIS Users/Edit): details and where they work, their roles,
 * where their branch-scoped roles apply, and deactivating or restoring the
 * account. Each card saves on its own and only shows what the viewer may do.
 */
function EditUserPage({
    user,
    roles,
    branches,
    permissions,
    scopes,
    scopeBranches,
    can,
    rolesLockedReason,
    urls,
    ...options
}: StaffFormOptions & {
    user: EditableUser;
    roles: PickableRole[];
    branches: BranchOption[];
    permissions: PermissionEntry[];
    scopes: BranchScopeRow[];
    scopeBranches: PickableBranch[];
    can: {
        update: boolean;
        assignRoles: boolean;
        deactivate: boolean;
        restore: boolean;
        resetPassword: boolean;
    };
    rolesLockedReason: string | null;
    urls: {
        index: string;
        update: string;
        roles: string;
        deactivate: string;
        restore: string;
        scopes: string;
        password: string;
        resetLink: string;
        twoFactor: string;
        permissions: string;
    };
}) {
    const { t } = useLocale();
    const { confirm } = useConfirm();

    const details = useForm<
        StaffFormData & {
            _method: 'put';
            avatar: File | null;
            remove_avatar: boolean;
        }
    >({
        _method: 'put',
        first_name: user.first_name ?? '',
        middle_name: user.middle_name ?? '',
        last_name: user.last_name ?? '',
        username: user.username ?? '',
        national_id: user.national_id ?? '',
        gender: user.gender ?? '',
        phone: localPhone(user.phone),
        institution: user.institution ?? '',
        department: user.department ?? '',
        job_title: user.job_title ?? '',
        employee_number: user.employee_number ?? '',
        manager_id: user.manager_id === null ? '' : String(user.manager_id),
        employment_status: user.employment_status ?? '',
        branch_id: user.branch_id === null ? '' : String(user.branch_id),
        locale: user.locale,
        timezone: user.timezone ?? options.defaultTimezone,
        theme: user.theme ?? options.themes[0] ?? 'system',
        notify_email: user.notify_email,
        avatar: null,
        remove_avatar: false,
    });
    const section = {
        data: details.data,
        setData: (key: keyof StaffFormData, value: string | boolean) =>
            details.setData(key, value as never),
        errors: details.errors,
        disabled: !can.update,
    };
    const permissionForm = useForm<{ permissions: string[] }>({
        permissions: user.permission_names,
    });
    const roleForm = useForm<{ roles: string[] }>({ roles: user.role_names });
    const passwordForm = useForm({ password: '', password_confirmation: '' });

    const selectedIds = roles
        .filter((role) => roleForm.data.roles.includes(role.name))
        .map((role) => role.id);
    const allGlobal =
        user.sees_everywhere &&
        roles
            .filter((role) => user.role_names.includes(role.name))
            .every((role) => role.scope === 'global');

    function toggle(id: number) {
        const role = roles.find((item) => item.id === id);
        if (!role) return;
        roleForm.setData(
            'roles',
            roleForm.data.roles.includes(role.name)
                ? roleForm.data.roles.filter((name) => name !== role.name)
                : [...roleForm.data.roles, role.name],
        );
    }

    function saveDetails(event: FormEvent) {
        event.preventDefault();
        details.transform((data) => ({
            ...data,
            phone: internationalPhone(data.phone),
            remove_avatar: data.remove_avatar ? 1 : 0,
            notify_email: data.notify_email ? 1 : 0,
        }));
        // A photo goes as a multipart POST; Laravel reads the PUT from _method.
        details.post(urls.update, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () =>
                details.setDefaults({
                    ...details.data,
                    avatar: null,
                    remove_avatar: false,
                }),
        });
    }

    function savePermissions(event: FormEvent) {
        event.preventDefault();
        permissionForm.put(urls.permissions, { preserveScroll: true });
    }

    function saveRoles(event: FormEvent) {
        event.preventDefault();
        roleForm.put(urls.roles, { preserveScroll: true });
    }

    async function deactivate() {
        const { confirmed } = await confirm({
            title: t('rbac.users.confirmDeactivate').replace(
                ':name',
                user.name,
            ),
            description: t('rbac.users.confirmDeactivateBody'),
            confirmLabel: t('rbac.users.deactivate'),
            variant: 'danger',
        });
        if (confirmed)
            router.post(urls.deactivate, {}, { preserveScroll: true });
    }

    function restore() {
        router.post(urls.restore, {}, { preserveScroll: true });
    }

    function setTemporaryPassword(event: FormEvent) {
        event.preventDefault();
        passwordForm.put(urls.password, {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    }

    function sendResetLink() {
        router.post(urls.resetLink, {}, { preserveScroll: true });
    }

    async function resetTwoFactor() {
        const { confirmed } = await confirm({
            title: t('rbac.users.confirmResetTwoFactor').replace(
                ':name',
                user.name,
            ),
            description: t('rbac.users.confirmResetTwoFactorBody'),
            confirmLabel: t('rbac.users.resetTwoFactor'),
            variant: 'danger',
        });
        if (confirmed) router.delete(urls.twoFactor, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    backHref={urls.index}
                    title={user.name}
                    description={user.email}
                    actions={
                        <span className="flex flex-wrap gap-1.5">
                            <span
                                className={badgeClassName(
                                    user.active ? 'success' : 'danger',
                                )}
                            >
                                {user.active
                                    ? t('rbac.users.active')
                                    : t('rbac.users.inactive')}
                            </span>
                            {user.is_staff && (
                                <span
                                    className={badgeClassName(
                                        user.two_factor ? 'neutral' : 'warning',
                                    )}
                                >
                                    {user.two_factor
                                        ? t('admin.users.two_factor_on')
                                        : t('admin.users.two_factor_off')}
                                </span>
                            )}
                        </span>
                    }
                />
            }
        >
            <Head title={user.name} />

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="min-w-0 space-y-5">
                    {!user.active && (
                        <Alert tone="warning">
                            {t('rbac.users.deactivatedNotice')
                                .replace(
                                    ':date',
                                    user.deactivated_at?.ethiopian_label ?? '',
                                )
                                .replace(':by', user.deactivated_by ?? '—')}
                        </Alert>
                    )}

                    {user.is_applicant ? (
                        <Alert tone="info">
                            {t('admin.users.errors.applicant')}
                        </Alert>
                    ) : (
                        <form
                            onSubmit={saveDetails}
                            noValidate
                            className="space-y-6 rounded-panel border border-(--app-border) bg-(--app-surface) p-6"
                        >
                            <Step
                                number={1}
                                title={t('rbac.users.personalSection')}
                            >
                                <AvatarPicker
                                    name={fullName(details.data) || user.name}
                                    file={details.data.avatar}
                                    current={user.avatar}
                                    removed={details.data.remove_avatar}
                                    error={details.errors.avatar}
                                    disabled={!can.update}
                                    onChange={(file) =>
                                        details.setData('avatar', file)
                                    }
                                    onRemove={(remove) =>
                                        details.setData('remove_avatar', remove)
                                    }
                                />
                                <IdentityFields {...section} />
                            </Step>
                            <Step
                                number={2}
                                title={t('rbac.users.accountSection')}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label={t('admin.users.fields.email')}
                                        description={t(
                                            'rbac.users.emailLocked',
                                        )}
                                    >
                                        {({ id, describedBy }) => (
                                            <Input
                                                id={id}
                                                aria-describedby={describedBy}
                                                disabled
                                                value={user.email}
                                                readOnly
                                            />
                                        )}
                                    </FormField>
                                    <UsernameField {...section} />
                                </div>
                            </Step>
                            <Step
                                number={3}
                                title={t('rbac.users.employmentSection')}
                            >
                                <EmploymentFields
                                    {...section}
                                    managers={options.managers}
                                    employmentStatuses={
                                        options.employmentStatuses
                                    }
                                    branches={branches}
                                />
                            </Step>
                            <Step
                                number={4}
                                title={t('rbac.users.preferencesSection')}
                            >
                                <PreferenceFields
                                    {...section}
                                    locales={options.locales}
                                    timezones={options.timezones}
                                    themes={options.themes}
                                />
                            </Step>
                            {can.update && (
                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        loading={details.processing}
                                        disabled={!details.isDirty}
                                    >
                                        {t('rbac.users.saveDetails')}
                                    </Button>
                                </div>
                            )}
                        </form>
                    )}

                    {!user.is_applicant && (
                        <form
                            onSubmit={saveRoles}
                            noValidate
                            className="space-y-4 rounded-panel border border-(--app-border) bg-(--app-surface) p-6"
                        >
                            <div>
                                <h2 className="text-sm font-semibold text-(--app-foreground)">
                                    {t('rbac.users.fields.roles')}
                                </h2>
                                <p className="mt-0.5 text-xs text-(--app-muted-foreground)">
                                    {t('rbac.users.rolesHelp')}
                                </p>
                            </div>
                            {can.assignRoles ? (
                                <>
                                    <RolePicker
                                        roles={roles}
                                        selected={selectedIds}
                                        onToggle={toggle}
                                        onClear={() =>
                                            roleForm.setData('roles', [])
                                        }
                                        error={
                                            roleForm.errors.roles ??
                                            Object.entries(
                                                roleForm.errors,
                                            ).find(([key]) =>
                                                key.startsWith('roles.'),
                                            )?.[1]
                                        }
                                    />
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            size="sm"
                                            loading={roleForm.processing}
                                            disabled={!roleForm.isDirty}
                                        >
                                            {t('admin.users.save_role')}
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <ul className="flex flex-wrap gap-1.5">
                                        {user.roles.map((role) => (
                                            <li
                                                key={role.name}
                                                className={badgeClassName(
                                                    'neutral',
                                                )}
                                            >
                                                {role.label}
                                            </li>
                                        ))}
                                    </ul>
                                    {rolesLockedReason && (
                                        <p className="text-xs text-(--app-muted-foreground)">
                                            {rolesLockedReason}
                                        </p>
                                    )}
                                </>
                            )}
                        </form>
                    )}

                    {!user.is_applicant &&
                        (permissions.length > 0 ||
                            user.permission_names.length > 0) && (
                            <form
                                onSubmit={savePermissions}
                                noValidate
                                className="space-y-4 rounded-panel border border-(--app-border) bg-(--app-surface) p-6"
                            >
                                <div>
                                    <h2 className="text-sm font-semibold text-(--app-foreground)">
                                        {t('rbac.users.extraPermissions')}
                                    </h2>
                                    <p className="mt-0.5 text-xs text-(--app-muted-foreground)">
                                        {t('rbac.users.extraPermissionsHelp')}
                                    </p>
                                </div>
                                <PermissionPicker
                                    permissions={permissions}
                                    selected={permissionForm.data.permissions}
                                    onChange={(names) =>
                                        permissionForm.setData(
                                            'permissions',
                                            names,
                                        )
                                    }
                                    disabled={!can.assignRoles}
                                    t={t as (key: string) => string}
                                />
                                <FieldError>
                                    {permissionForm.errors.permissions}
                                </FieldError>
                                {can.assignRoles ? (
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            size="sm"
                                            loading={permissionForm.processing}
                                            disabled={!permissionForm.isDirty}
                                        >
                                            {t('rbac.users.savePermissions')}
                                        </Button>
                                    </div>
                                ) : (
                                    rolesLockedReason && (
                                        <p className="text-xs text-(--app-muted-foreground)">
                                            {rolesLockedReason}
                                        </p>
                                    )
                                )}
                            </form>
                        )}

                    <section className="space-y-4 rounded-panel border border-(--app-border) bg-(--app-surface) p-6">
                        <div>
                            <h2 className="text-sm font-semibold text-(--app-foreground)">
                                {t('rbac.users.recordSection')}
                            </h2>
                            <p className="mt-0.5 text-xs text-(--app-muted-foreground)">
                                {t('rbac.users.recordSectionHelp')}
                            </p>
                        </div>
                        <RecordList
                            items={[
                                {
                                    label: t('rbac.users.fields.userId'),
                                    value: `#${user.id}`,
                                },
                                {
                                    label: t('rbac.users.fields.accountStatus'),
                                    value: user.active
                                        ? t('rbac.users.active')
                                        : t('rbac.users.inactive'),
                                },
                                {
                                    label: t(
                                        'rbac.users.fields.emailVerification',
                                    ),
                                    value: user.record.email_verified_at
                                        ? `${t('rbac.users.record.verified')} · ${user.record.email_verified_at.ethiopian_label}`
                                        : t('rbac.users.record.notVerified'),
                                },
                                {
                                    label: t('rbac.users.fields.twoFactor'),
                                    value: user.record.two_factor
                                        ? t('rbac.users.record.twoFactorOn')
                                        : t('rbac.users.record.twoFactorOff'),
                                },
                                {
                                    label: t('rbac.users.fields.lastLogin'),
                                    value: user.record.last_login_at
                                        ? `${user.record.last_login_at.ethiopian_label} ${user.record.last_login_time ?? ''}`
                                        : t('rbac.users.record.never'),
                                },
                                {
                                    label: t('rbac.users.fields.lastLoginIp'),
                                    value:
                                        user.record.last_login_ip ??
                                        t('rbac.users.record.none'),
                                },
                                {
                                    label: t('rbac.users.fields.createdBy'),
                                    value:
                                        user.record.created_by ??
                                        t('rbac.users.record.none'),
                                },
                                {
                                    label: t('rbac.users.fields.createdAt'),
                                    value:
                                        user.record.created_at
                                            ?.ethiopian_label ??
                                        t('rbac.users.record.none'),
                                },
                                {
                                    label: t('rbac.users.fields.updatedAt'),
                                    value:
                                        user.record.updated_at
                                            ?.ethiopian_label ??
                                        t('rbac.users.record.none'),
                                },
                                {
                                    label: t('rbac.users.fields.deactivatedAt'),
                                    value:
                                        user.deactivated_at?.ethiopian_label ??
                                        t('rbac.users.record.none'),
                                },
                            ]}
                        />
                    </section>
                </div>

                <div className="space-y-5">
                    {user.is_staff && (
                        <BranchScopesCard
                            scopes={scopes}
                            branches={scopeBranches}
                            storeUrl={urls.scopes}
                            canManage={can.assignRoles}
                            allGlobal={allGlobal}
                        />
                    )}

                    {can.resetPassword && (
                        <aside className="space-y-4 rounded-panel border border-(--app-border) bg-(--app-surface) p-5">
                            <div>
                                <h2 className="text-sm font-semibold text-(--app-foreground)">
                                    {t('rbac.users.signIn')}
                                </h2>
                                <p className="mt-0.5 text-xs text-(--app-muted-foreground)">
                                    {t('rbac.users.signInHelp')}
                                </p>
                            </div>

                            <form
                                onSubmit={setTemporaryPassword}
                                noValidate
                                className="space-y-3"
                            >
                                <FormField
                                    label={t('rbac.users.temporaryPassword')}
                                    required
                                    error={passwordForm.errors.password}
                                    description={t(
                                        'rbac.users.temporaryPasswordHelp',
                                    )}
                                >
                                    {({ id, describedBy, invalid }) => (
                                        <Input
                                            id={id}
                                            type="password"
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            autoComplete="new-password"
                                            value={passwordForm.data.password}
                                            onChange={(event) =>
                                                passwordForm.setData(
                                                    'password',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    label={t('rbac.users.confirmPassword')}
                                    required
                                    error={
                                        passwordForm.errors
                                            .password_confirmation
                                    }
                                >
                                    {({ id, describedBy, invalid }) => (
                                        <Input
                                            id={id}
                                            type="password"
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            autoComplete="new-password"
                                            value={
                                                passwordForm.data
                                                    .password_confirmation
                                            }
                                            onChange={(event) =>
                                                passwordForm.setData(
                                                    'password_confirmation',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                                <Button
                                    type="submit"
                                    size="sm"
                                    loading={passwordForm.processing}
                                    disabled={passwordForm.data.password === ''}
                                >
                                    {t('rbac.users.setTemporaryPassword')}
                                </Button>
                            </form>

                            <div className="space-y-2 border-t border-(--app-border) pt-4">
                                <p className="text-xs text-(--app-muted-foreground)">
                                    {t('rbac.users.sendResetLinkHelp')}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={sendResetLink}
                                >
                                    {t('rbac.users.sendResetLink')}
                                </Button>
                            </div>

                            {user.two_factor && (
                                <div className="space-y-2 border-t border-(--app-border) pt-4">
                                    <p className="text-xs text-(--app-muted-foreground)">
                                        {t('rbac.users.resetTwoFactorHelp')}
                                    </p>
                                    <Button
                                        variant="destructiveOutline"
                                        size="sm"
                                        onClick={() => void resetTwoFactor()}
                                    >
                                        {t('rbac.users.resetTwoFactor')}
                                    </Button>
                                </div>
                            )}
                        </aside>
                    )}

                    {(can.deactivate || can.restore) && (
                        <aside className="space-y-3 rounded-panel border border-(--app-border) bg-(--app-surface) p-5">
                            <h2 className="text-sm font-semibold text-(--app-foreground)">
                                {t('rbac.users.account')}
                            </h2>
                            {user.active ? (
                                <>
                                    <p className="text-xs text-(--app-muted-foreground)">
                                        {t('rbac.users.deactivateHelp')}
                                    </p>
                                    {can.deactivate && (
                                        <Button
                                            variant="destructiveOutline"
                                            size="sm"
                                            onClick={() => void deactivate()}
                                        >
                                            {t('rbac.users.deactivate')}
                                        </Button>
                                    )}
                                </>
                            ) : (
                                <>
                                    <p className="text-xs text-(--app-muted-foreground)">
                                        {t('rbac.users.restoreHelp')}
                                    </p>
                                    {can.restore && (
                                        <Button size="sm" onClick={restore}>
                                            {t('rbac.users.restore')}
                                        </Button>
                                    )}
                                </>
                            )}
                        </aside>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

/** useConfirm() runs in the page, above the layout's own provider, so the page brings one. */
export default function EditUser(props: Parameters<typeof EditUserPage>[0]) {
    return (
        <ConfirmProvider>
            <EditUserPage {...props} />
        </ConfirmProvider>
    );
}
