import { Head } from '@inertiajs/react';
import PageHeader from '@/shell/Components/PageHeader';
import type {
    PermissionEntry,
    RoleScope,
} from '@/shell/Components/Roles/PermissionPicker';
import RoleForm from '@/shell/Components/Roles/RoleForm';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

type RoleData = {
    id: number;
    name: string;
    label: string;
    name_am: string | null;
    scope_type: RoleScope;
    permissions: string[];
    is_protected: boolean;
    is_system: boolean;
    users_count: number;
};

/** One role's names, scope type and permissions (EUISIS Roles/Edit). */
export default function EditRole({
    role,
    permissions,
    can,
    urls,
}: {
    role: RoleData;
    permissions: PermissionEntry[];
    can: { assignPermissions: boolean; manageGlobalRoles: boolean };
    urls: { index: string; update: string };
}) {
    const { t } = useLocale();

    return (
        <AuthenticatedLayout
            contentWidth="form"
            header={
                <PageHeader
                    backHref={urls.index}
                    title={t('rbac.roles.editTitle')}
                    description={t('rbac.roles.editIntro')
                        .replace(':name', role.label)
                        .replace(':count', String(role.users_count))}
                />
            }
        >
            <Head title={`${t('rbac.roles.editTitle')}: ${role.label}`} />
            <RoleForm
                initial={{
                    name: role.name,
                    name_am: role.name_am ?? '',
                    scope_type: role.scope_type,
                    permissions: role.permissions,
                }}
                permissions={permissions}
                can={can}
                submitUrl={urls.update}
                method="put"
                cancelUrl={urls.index}
                submitLabel={t('rbac.roles.save')}
                isProtected={role.is_protected}
                isSystem={role.is_system}
            />
        </AuthenticatedLayout>
    );
}
