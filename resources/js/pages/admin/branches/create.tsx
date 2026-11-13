import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { BranchForm } from '@/components/branch-form';
import type { BranchFormData } from '@/components/branch-form';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, store } from '@/routes/admin/branches';

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

    return (
        <FormPageLayout
            title={t('admin.branches.create_title')}
            backHref={index.url()}
        >
            <BranchForm
                form={form}
                onSubmit={submit}
                cancelHref={index.url()}
                codeLocked={false}
            />
        </FormPageLayout>
    );
}
