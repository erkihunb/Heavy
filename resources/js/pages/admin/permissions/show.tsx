import { Head, Link } from '@inertiajs/react';
import PageHeader from '@/shell/Components/PageHeader';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
    EmptyState,
    badgeClassName,
    cx,
} from '@/shell/Components/ui';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import { CRITICAL_PERMISSIONS } from '@/shell/lib/permissionGroups';

type HoldingRole = {
    id: number;
    label: string;
    name: string;
    scope_type: 'branch' | 'global';
    users_count: number;
    editUrl: string | null;
};

/** One permission: what it means and which roles hold it (EUISIS Permissions/Show). */
export default function ShowPermission({
    permission,
    roles,
    urls,
}: {
    permission: {
        name: string;
        label: string;
        description: string | null;
    };
    roles: HoldingRole[];
    urls: { index: string };
}) {
    const { t } = useLocale();

    return (
        <AuthenticatedLayout
            contentWidth="form"
            header={
                <PageHeader
                    backHref={urls.index}
                    title={permission.label}
                    description={permission.description ?? undefined}
                />
            }
            breadcrumbs={[
                { label: t('rbac.permissions.title'), href: urls.index },
                { label: permission.name },
            ]}
        >
            <Head title={permission.label} />

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <Card>
                    <dl className="space-y-3 text-sm">
                        <div>
                            <dt className="text-xs text-(--app-muted-foreground)">
                                {t('rbac.permissions.permission')}
                            </dt>
                            <dd className="font-mono break-all text-(--app-foreground)">
                                {permission.name}
                            </dd>
                        </div>
                        {CRITICAL_PERMISSIONS.has(permission.name) && (
                            <div>
                                <span className={badgeClassName('danger')}>
                                    {t('rbac.permissions.critical')}
                                </span>
                                <p className="mt-1 text-xs text-(--app-muted-foreground)">
                                    {t('rbac.permissions.criticalWarning')}
                                </p>
                            </div>
                        )}
                    </dl>
                </Card>

                <Card className="p-0">
                    <CardHeader className="mb-0 border-b border-(--app-border) px-5 py-4">
                        <div>
                            <CardTitle>
                                {t('rbac.permissions.heldBy')}
                            </CardTitle>
                            <CardDescription>
                                {t('rbac.permissions.heldByIntro')}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    {roles.length === 0 ? (
                        <EmptyState
                            title={t('rbac.permissions.unassigned')}
                            description={t('rbac.permissions.unassignedHelp')}
                        />
                    ) : (
                        <ul className="divide-y divide-(--app-border)">
                            {roles.map((role) => (
                                <li
                                    key={role.id}
                                    className="flex items-center gap-3 px-5 py-3"
                                >
                                    <span className="min-w-0 flex-1">
                                        {role.editUrl ? (
                                            <Link
                                                href={role.editUrl}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {role.label}
                                            </Link>
                                        ) : (
                                            <span className="font-medium text-(--app-foreground)">
                                                {role.label}
                                            </span>
                                        )}
                                        <span className="ml-2 font-mono text-xs text-(--app-muted-foreground)">
                                            {role.name}
                                        </span>
                                    </span>
                                    <span
                                        className={cx(
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            role.scope_type === 'global'
                                                ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
                                                : 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
                                        )}
                                    >
                                        {role.scope_type === 'global'
                                            ? t('rbac.roles.globalRole')
                                            : t('rbac.roles.branchRole')}
                                    </span>
                                    <span className="text-xs text-(--app-muted-foreground) tabular-nums">
                                        {t('rbac.roles.usersN').replace(
                                            ':count',
                                            String(role.users_count),
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
