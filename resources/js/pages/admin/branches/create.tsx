import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { BranchForm } from '@/components/branch-form';
import type { BranchFormData } from '@/components/branch-form';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, index, store } from '@/routes/admin/branches';
import type { BreadcrumbItem } from '@/types';

export default function BranchesCreate() {
    const { t } = useTranslation();
    const form = useForm<BranchFormData>({
        code: '',
        name_am: '',
        name_en: '',
        is_active: true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.branches.title'), href: index() },
        { title: t('admin.branches.create_title'), href: create() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.branches.create_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader title={t('admin.branches.create_title')} />
                <BranchForm
                    form={form}
                    onSubmit={submit}
                    cancelHref={index.url()}
                    codeLocked={false}
                />
            </div>
        </AppLayout>
    );
}
