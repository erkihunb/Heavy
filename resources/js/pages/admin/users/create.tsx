import { Head, Link, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, index, store } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';
import type { Role } from '@/types/enums';

type Props = {
    roles: Role[];
};

export default function UsersCreate({ roles }: Props) {
    const { t } = useTranslation();
    const form = useForm({ name: '', email: '', role: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.users.title'), href: index() },
        { title: t('admin.users.create_title'), href: create() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.users.create_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('admin.users.create_title')}
                    description={t('admin.users.create_intro')}
                />

                <form
                    onSubmit={submit}
                    noValidate
                    className="max-w-xl space-y-5 rounded-card border border-border bg-card p-5"
                >
                    <FormField
                        id="name"
                        label={t('admin.users.fields.name')}
                        error={form.errors.name}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                autoComplete="off"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        )}
                    </FormField>

                    <FormField
                        id="email"
                        label={t('admin.users.fields.email')}
                        error={form.errors.email}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                type="email"
                                autoComplete="off"
                                translate="no"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                            />
                        )}
                    </FormField>

                    <FormField
                        id="role"
                        label={t('admin.users.fields.role')}
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
                                <option value="" disabled>
                                    —
                                </option>
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {t(`roles.${role}.name`)}
                                    </option>
                                ))}
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
                                <UserPlus aria-hidden="true" />
                            )}
                            {t('admin.users.create')}
                        </Button>
                        <Button asChild size="lg" variant="ghost">
                            <Link href={index()}>{t('common.cancel')}</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
