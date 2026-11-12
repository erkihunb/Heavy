import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PermissionPicker, {
    RoleScopeField,
} from '@/shell/Components/Roles/PermissionPicker';
import type {
    PermissionEntry,
    RoleScope,
} from '@/shell/Components/Roles/PermissionPicker';
import {
    Alert,
    Button,
    FormField,
    Input,
    buttonClassName,
} from '@/shell/Components/ui';
import { useLocale } from '@/shell/hooks/useLocale';
import type { PermissionModule } from '@/shell/lib/permissionGroups';

export type RoleFormData = {
    name: string;
    name_am: string;
    scope_type: RoleScope;
    permissions: string[];
};

/**
 * The role editor shared by Create and Edit (EUISIS Roles/Create, Roles/Edit):
 * names and scope type above, the grouped permission picker below.
 */
export default function RoleForm({
    initial,
    permissions,
    modules,
    can,
    submitUrl,
    method,
    cancelUrl,
    submitLabel,
    isProtected = false,
    isSystem = false,
}: {
    initial: RoleFormData;
    permissions: Record<string, PermissionEntry[]>;
    modules: PermissionModule[];
    can: { assignPermissions: boolean; manageGlobalRoles: boolean };
    submitUrl: string;
    method: 'post' | 'put';
    cancelUrl: string;
    submitLabel: string;
    /** The administrator role: it always holds every permission. */
    isProtected?: boolean;
    /** A default role: its code name is used by the application and cannot change. */
    isSystem?: boolean;
}) {
    const { t } = useLocale();
    const form = useForm<RoleFormData>(initial);
    const pickerDisabled = !can.assignPermissions || isProtected;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(method, submitUrl, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-5">
            <div className="rounded-panel border border-(--app-border) bg-(--app-surface) p-6">
                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
                    <div className="space-y-4">
                        <FormField
                            label={t('rbac.roles.name')}
                            required
                            error={form.errors.name}
                            description={
                                isSystem
                                    ? t('rbac.roles.systemNameLocked')
                                    : t('rbac.roles.nameHelp')
                            }
                        >
                            {({ id, describedBy, invalid }) => (
                                <Input
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    disabled={isSystem}
                                    placeholder={t(
                                        'rbac.roles.namePlaceholder',
                                    )}
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                            )}
                        </FormField>
                        <FormField
                            label={t('rbac.roles.nameAm')}
                            error={form.errors.name_am}
                        >
                            {({ id, describedBy, invalid }) => (
                                <Input
                                    id={id}
                                    lang="am"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    value={form.data.name_am}
                                    onChange={(e) =>
                                        form.setData('name_am', e.target.value)
                                    }
                                />
                            )}
                        </FormField>
                    </div>
                    <RoleScopeField
                        value={form.data.scope_type}
                        onChange={(scope) => form.setData('scope_type', scope)}
                        canManageGlobal={can.manageGlobalRoles}
                        error={form.errors.scope_type}
                        t={t}
                    />
                </div>
            </div>

            <div className="rounded-panel border border-(--app-border) bg-(--app-surface) p-6">
                <h2 className="mb-4 text-sm font-semibold text-(--app-foreground)">
                    {t('rbac.roles.permissionsSection')}
                    <span className="ml-2 text-xs font-normal text-(--app-muted-foreground) tabular-nums">
                        {t('rbac.roles.selectedPermissions').replace(
                            ':count',
                            String(form.data.permissions.length),
                        )}
                    </span>
                </h2>
                {isProtected && (
                    <Alert tone="info" className="mb-4">
                        {t('rbac.roles.protectedNotice')}
                    </Alert>
                )}
                {!isProtected && !can.assignPermissions && (
                    <Alert tone="warning" className="mb-4">
                        {t('rbac.roles.cannotAssignPermissions')}
                    </Alert>
                )}
                <PermissionPicker
                    permissions={permissions}
                    modules={modules}
                    selected={form.data.permissions}
                    onChange={(names) => form.setData('permissions', names)}
                    disabled={pickerDisabled}
                    t={t}
                />
                {form.errors.permissions && (
                    <p className="mt-2 text-xs text-red-600 dark:text-red-400">
                        {form.errors.permissions}
                    </p>
                )}
            </div>

            <div className="flex items-center justify-end gap-3">
                <Link
                    href={cancelUrl}
                    className={buttonClassName({ variant: 'ghost' })}
                >
                    {t('shell.common.cancel')}
                </Link>
                <Button type="submit" loading={form.processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
