import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { BranchForm } from '@/components/branch-form';
import type { BranchFormData } from '@/components/branch-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, update } from '@/routes/admin/branches';
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

    return (
        <FormPageLayout
            title={t('admin.branches.edit_title')}
            backHref={index.url()}
        >
            <BranchForm
                form={form}
                onSubmit={submit}
                cancelHref={index.url()}
                codeLocked
            />
        </FormPageLayout>
    );
}
