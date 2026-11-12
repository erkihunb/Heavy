import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ConfirmProvider from '@/shell/Components/ConfirmProvider';
import PageHeader from '@/shell/Components/PageHeader';
import {
    Alert,
    Button,
    FormField,
    Input,
    Select,
    badgeClassName,
} from '@/shell/Components/ui';
import type { PickableBranch } from '@/shell/Components/Users/BranchPicker';
import BranchScopesCard from '@/shell/Components/Users/BranchScopesCard';
import type { BranchScopeRow } from '@/shell/Components/Users/BranchScopesCard';
import RolePicker from '@/shell/Components/Users/RolePicker';
import type { PickableRole } from '@/shell/Components/Users/RolePicker';
import { useConfirm } from '@/shell/hooks/useConfirm';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import type { BranchOption } from './create';
import type { UserRow } from './index';

type EditableUser = UserRow & {
    branch_id: number | null;
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
    scopes,
    scopeBranches,
    can,
    rolesLockedReason,
    urls,
}: {
    user: EditableUser;
    roles: PickableRole[];
    branches: BranchOption[];
    scopes: BranchScopeRow[];
    scopeBranches: PickableBranch[];
    can: {
        update: boolean;
        assignRoles: boolean;
        deactivate: boolean;
        restore: boolean;
    };
    rolesLockedReason: string | null;
    urls: {
        index: string;
        update: string;
        roles: string;
        deactivate: string;
        restore: string;
        scopes: string;
    };
}) {
    const { t } = useLocale();
    const { confirm } = useConfirm();

    const details = useForm({
        name: user.name,
        branch_id: user.branch_id === null ? '' : String(user.branch_id),
    });
    const roleForm = useForm<{ roles: string[] }>({ roles: user.role_names });

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
        details.put(urls.update, { preserveScroll: true });
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
                            className="space-y-4 rounded-panel border border-(--app-border) bg-(--app-surface) p-6"
                        >
                            <h2 className="text-sm font-semibold text-(--app-foreground)">
                                {t('rbac.users.details')}
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label={t('admin.users.fields.name')}
                                    required
                                    error={details.errors.name}
                                >
                                    {({ id, describedBy, invalid }) => (
                                        <Input
                                            id={id}
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            disabled={!can.update}
                                            value={details.data.name}
                                            onChange={(event) =>
                                                details.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    label={t('admin.users.fields.email')}
                                    description={t('rbac.users.emailLocked')}
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
                                <FormField
                                    label={t('rbac.users.worksAt')}
                                    error={details.errors.branch_id}
                                    description={t('rbac.users.worksAtHelp')}
                                    className="sm:col-span-2"
                                >
                                    {({ id, describedBy, invalid }) => (
                                        <Select
                                            id={id}
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            disabled={!can.update}
                                            value={details.data.branch_id}
                                            onChange={(event) =>
                                                details.setData(
                                                    'branch_id',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                {t('rbac.scopes.headOffice')}
                                            </option>
                                            {branches.map((branch) => (
                                                <option
                                                    key={branch.id}
                                                    value={String(branch.id)}
                                                >
                                                    {branch.code} ·{' '}
                                                    {branch.name}
                                                    {branch.is_active
                                                        ? ''
                                                        : ` (${t('admin.users.branch_closed')})`}
                                                </option>
                                            ))}
                                        </Select>
                                    )}
                                </FormField>
                            </div>
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
