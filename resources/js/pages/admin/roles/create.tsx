import { Head } from '@inertiajs/react';
import PageHeader from '@/shell/Components/PageHeader';
import type { PermissionEntry } from '@/shell/Components/Roles/PermissionPicker';
import RoleForm from '@/shell/Components/Roles/RoleForm';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

/** A new role (EUISIS Roles/Create). */
export default function CreateRole({
    permissions,
    can,
    urls,
}: {
    permissions: PermissionEntry[];
    can: { assignPermissions: boolean; manageGlobalRoles: boolean };
    urls: { index: string; store: string };
}) {
    const { t } = useLocale();

    return (
        <AuthenticatedLayout
            contentWidth="form"
            header={
                <PageHeader
                    backHref={urls.index}
                    title={t('rbac.roles.createTitle')}
                    description={t('rbac.roles.createIntro')}
                />
            }
        >
            <Head title={t('rbac.roles.createTitle')} />
            <RoleForm
                initial={{
                    name: '',
                    name_am: '',
                    scope_type: 'branch',
                    permissions: [],
                }}
                permissions={permissions}
                can={can}
                submitUrl={urls.store}
                method="post"
                cancelUrl={urls.index}
                submitLabel={t('rbac.roles.createAction')}
            />
        </AuthenticatedLayout>
    );
}
