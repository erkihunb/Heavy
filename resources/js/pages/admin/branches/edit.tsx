import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { BranchForm } from '@/components/branch-form';
import type { BranchFormData } from '@/components/branch-form';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { edit, index, update } from '@/routes/admin/branches';
import type { BreadcrumbItem } from '@/types';
import type { AdminBranch } from '@/types/admin';

type Props = {
    branch: AdminBranch;
};

export default function BranchesEdit({ branch }: Props) {
    const { t } = useTranslation();
    const form = useForm<BranchFormData>({
        code: branch.code,
        name_am: branch.name_am,
        name_en: branch.name_en,
        is_active: branch.is_active,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url({ branch: branch.id }), { preserveScroll: true });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('admin.branches.title'), href: index() },
        {
            title: t('admin.branches.edit_title'),
            href: edit({ branch: branch.id }),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.branches.edit_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader title={t('admin.branches.edit_title')} />
                <BranchForm
                    form={form}
                    onSubmit={submit}
                    cancelHref={index.url()}
                    codeLocked
                />
            </div>
        </AppLayout>
    );
}
