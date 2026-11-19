import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { FormActions, FormSection } from '@/components/form-layout';
import { Input } from '@/components/ui/input';
import FormPageLayout from '@/layouts/form-page-layout';
import { useTranslation } from '@/lib/i18n';
import { index, show, update } from '@/routes/admin/organizations';
import type { TranslationKey } from '@/types/translations';
import type { AdminOrganization } from './index';

type Props = {
    organization: AdminOrganization;
};

type FormData = {
    name_am: string;
    name_en: string;
    tin: string;
    phone: string;
    email: string;
    address: string;
};

/** Correct an organization: its names, TIN, phone, e-mail and address. */
export default function OrganizationEdit({ organization }: Props) {
    const { t } = useTranslation();
    const back = show.url({ organization: organization.id });
    const form = useForm<FormData>({
        name_am: organization.name_am,
        name_en: organization.name_en ?? '',
        tin: organization.tin ?? '',
        phone: organization.phone,
        email: organization.email,
        address: organization.address,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url({ organization: organization.id }), {
            preserveScroll: true,
        });
    };

    const fields: {
        key: keyof FormData;
        label: TranslationKey;
        required?: boolean;
        type?: string;
        lang?: string;
        numeric?: boolean;
    }[] = [
        {
            key: 'name_am',
            label: 'organizations.fields.name_am',
            required: true,
            lang: 'am',
        },
        { key: 'name_en', label: 'organizations.fields.name_en', lang: 'en' },
        { key: 'tin', label: 'organizations.fields.tin', numeric: true },
        {
            key: 'phone',
            label: 'organizations.fields.phone',
            required: true,
            type: 'tel',
        },
        {
            key: 'email',
            label: 'organizations.fields.email',
            required: true,
            type: 'email',
        },
        { key: 'address', label: 'organizations.fields.address' },
    ];

    return (
        <FormPageLayout
            title={t('organizations.edit_title')}
            description={organization.name_am}
            backHref={back}
            breadcrumbs={[
                { label: t('organizations.title'), href: index().url },
                { label: organization.name_am, href: back },
                { label: t('organizations.edit_title') },
            ]}
        >
            <form onSubmit={submit} noValidate className="space-y-5">
                <FormSection>
                    <div className="grid items-start gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {fields.map((field) => (
                            <FormField
                                key={field.key}
                                id={field.key}
                                required={field.required}
                                label={t(field.label)}
                                error={form.errors[field.key]}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        type={field.type ?? 'text'}
                                        lang={field.lang}
                                        inputMode={
                                            field.numeric
                                                ? 'numeric'
                                                : undefined
                                        }
                                        maxLength={field.numeric ? 10 : 255}
                                        value={form.data[field.key]}
                                        onChange={(event) =>
                                            form.setData(
                                                field.key,
                                                field.numeric
                                                    ? event.target.value.replace(
                                                          /\D/g,
                                                          '',
                                                      )
                                                    : event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        ))}
                    </div>
                </FormSection>

                <FormActions
                    cancelHref={back}
                    cancelLabel={t('common.cancel')}
                    submitLabel={t('organizations.save')}
                    processing={form.processing}
                    icon={<Save aria-hidden="true" />}
                />
            </form>
        </FormPageLayout>
    );
}
