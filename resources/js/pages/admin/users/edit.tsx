import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { edit, index, role as updateRole } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';
import type { AdminUser } from '@/types/admin';
import type { Role } from '@/types/enums';

type Props = {
    user: AdminUser;
    roles: Role[];
};

export default function UsersEdit({ user, roles }: Props) {
    const { t } = useTranslation();
    const form = useForm<{ role: string }>({ role: user.roles[0] ?? '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(updateRole.url({ user: user.id }), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.users.title'), href: index() },
        { title: user.name, href: edit({ user: user.id }) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.users.edit_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('admin.users.edit_title')}
                    description={`${user.name} · ${user.email}`}
                />

                {user.can_change_role ? (
                    <form
                        onSubmit={submit}
                        noValidate
                        className="max-w-xl space-y-5 rounded-card border border-border bg-card p-5"
                    >
                        <FormField
                            id="role"
                            label={t('admin.users.fields.role')}
                            hint={t('admin.users.remove_access_hint')}
                            error={form.errors.role}
                        >
                            {(control) => (
                                <NativeSelect
                                    {...control}
                                    value={form.data.role}
                                    onChange={(event) =>
                                        form.setData('role', event.target.value)
                                    }
                                >
                                    {roles.map((role) => (
                                        <option key={role} value={role}>
                                            {t(`roles.${role}.name`)}
                                        </option>
                                    ))}
                                    <option value="">
                                        {t('admin.users.remove_access')}
                                    </option>
                                </NativeSelect>
                            )}
                        </FormField>

                        <div className="flex flex-wrap gap-3">
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                            >
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    <Save aria-hidden="true" />
                                )}
                                {t('admin.users.save_role')}
                            </Button>
                            <Button asChild size="lg" variant="ghost">
                                <Link href={index()}>{t('common.cancel')}</Link>
                            </Button>
                        </div>
                    </form>
                ) : (
                    <p className="rounded-card border border-border bg-muted p-4 font-medium">
                        {user.role_locked_reason}
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
