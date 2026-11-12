import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PageHeader from '@/shell/Components/PageHeader';
import {
    Button,
    FormField,
    Input,
    Select,
    buttonClassName,
} from '@/shell/Components/ui';
import RolePicker from '@/shell/Components/Users/RolePicker';
import type { PickableRole } from '@/shell/Components/Users/RolePicker';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

export type BranchOption = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

/**
 * A new staff account (EUISIS Users/Create): name, e-mail, where the person
 * works, and one or more roles. They get an e-mail with a link to choose
 * their password; their branch scope starts from where they work.
 */
export default function CreateUser({
    roles,
    branches,
    urls,
}: {
    roles: PickableRole[];
    branches: BranchOption[];
    urls: { index: string; store: string };
}) {
    const { t } = useLocale();
    const form = useForm<{
        name: string;
        email: string;
        branch_id: string;
        roles: string[];
    }>({ name: '', email: '', branch_id: '', roles: [] });

    const selectedIds = roles
        .filter((role) => form.data.roles.includes(role.name))
        .map((role) => role.id);

    function toggle(id: number) {
        const role = roles.find((item) => item.id === id);
        if (!role) return;
        form.setData(
            'roles',
            form.data.roles.includes(role.name)
                ? form.data.roles.filter((name) => name !== role.name)
                : [...form.data.roles, role.name],
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(urls.store, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout
            contentWidth="form"
            header={
                <PageHeader
                    backHref={urls.index}
                    title={t('admin.users.create_title')}
                    description={t('admin.users.create_intro')}
                />
            }
        >
            <Head title={t('admin.users.create_title')} />

            <form onSubmit={submit} noValidate className="space-y-5">
                <section className="space-y-4 rounded-panel border border-(--app-border) bg-(--app-surface) p-6">
                    <h2 className="text-sm font-semibold text-(--app-foreground)">
                        {t('rbac.users.details')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label={t('admin.users.fields.name')}
                            required
                            error={form.errors.name}
                        >
                            {({ id, describedBy, invalid }) => (
                                <Input
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    autoComplete="off"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                            )}
                        </FormField>
                        <FormField
                            label={t('admin.users.fields.email')}
                            required
                            error={form.errors.email}
                        >
                            {({ id, describedBy, invalid }) => (
                                <Input
                                    id={id}
                                    type="email"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    autoComplete="off"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>
                        <FormField
                            label={t('rbac.users.worksAt')}
                            error={form.errors.branch_id}
                            description={t('rbac.users.worksAtHelp')}
                            className="sm:col-span-2"
                        >
                            {({ id, describedBy, invalid }) => (
                                <Select
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    value={form.data.branch_id}
                                    onChange={(event) =>
                                        form.setData(
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
                                            {branch.code} · {branch.name}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </FormField>
                    </div>
                </section>

                <section className="space-y-4 rounded-panel border border-(--app-border) bg-(--app-surface) p-6">
                    <div>
                        <h2 className="text-sm font-semibold text-(--app-foreground)">
                            {t('rbac.users.fields.roles')}
                        </h2>
                        <p className="mt-0.5 text-xs text-(--app-muted-foreground)">
                            {t('rbac.users.rolesHelp')}
                        </p>
                    </div>
                    <RolePicker
                        roles={roles}
                        selected={selectedIds}
                        onToggle={toggle}
                        onClear={() => form.setData('roles', [])}
                        error={
                            form.errors.roles ??
                            Object.entries(form.errors).find(([key]) =>
                                key.startsWith('roles.'),
                            )?.[1]
                        }
                    />
                </section>

                <div className="flex justify-end gap-2">
                    <Link
                        href={urls.index}
                        className={buttonClassName({ variant: 'ghost' })}
                    >
                        {t('shell.common.cancel')}
                    </Link>
                    <Button type="submit" loading={form.processing}>
                        {t('admin.users.create')}
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
