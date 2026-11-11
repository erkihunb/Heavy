import { Head, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { show, store, update } from '@/routes/organization';
import type { BreadcrumbItem, Organization } from '@/types';

type Props = {
    organization: Organization | null;
    canUpdate: boolean;
};

export default function OrganizationPage({ organization, canUpdate }: Props) {
    const { t } = useTranslation();

    const form = useForm({
        name_am: organization?.name_am ?? '',
        name_en: organization?.name_en ?? '',
        tin: organization?.tin ?? '',
        phone: organization?.phone ?? '',
        email: organization?.email ?? '',
        address: organization?.address ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            // Show what was actually stored (for example the phone number in
            // international format) rather than what was typed.
            onSuccess: (page: { props: Record<string, unknown> }) => {
                const saved = page.props.organization as Organization | null;

                if (saved) {
                    form.setData({
                        name_am: saved.name_am,
                        name_en: saved.name_en ?? '',
                        tin: saved.tin ?? '',
                        phone: saved.phone,
                        email: saved.email,
                        address: saved.address,
                    });
                }
            },
        };

        if (organization) {
            form.put(update.url(), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('organization.title'), href: show() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('organization.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('organization.title')}
                    description={
                        organization
                            ? t('organization.intro_edit')
                            : t('organization.intro_create')
                    }
                />

                {!canUpdate && (
                    <p className="flex items-center gap-3 rounded-2xl border border-border bg-muted px-4 py-3 font-medium">
                        <Lock aria-hidden="true" className="size-5 shrink-0" />
                        {t('organization.read_only')}
                    </p>
                )}

                <form
                    onSubmit={submit}
                    noValidate
                    className="max-w-2xl rounded-3xl border border-border bg-card p-6 sm:p-8"
                >
                    <fieldset disabled={!canUpdate} className="space-y-6">
                        <FormField
                            id="name_am"
                            label={t('organization.fields.name_am')}
                            error={form.errors.name_am}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    lang="am"
                                    value={form.data.name_am}
                                    autoComplete="organization"
                                    maxLength={255}
                                    onChange={(event) =>
                                        form.setData(
                                            'name_am',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>

                        <FormField
                            id="name_en"
                            label={t('organization.fields.name_en')}
                            error={form.errors.name_en}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    lang="en"
                                    value={form.data.name_en}
                                    maxLength={255}
                                    onChange={(event) =>
                                        form.setData(
                                            'name_en',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>

                        <div className="grid gap-6 sm:grid-cols-2">
                            <FormField
                                id="tin"
                                label={t('organization.fields.tin')}
                                hint={t('organization.fields.tin_hint')}
                                error={form.errors.tin}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        value={form.data.tin}
                                        inputMode="numeric"
                                        className="tabular-nums"
                                        onChange={(event) =>
                                            form.setData(
                                                'tin',
                                                event.target.value
                                                    .replace(/\D/g, '')
                                                    .slice(0, 10),
                                            )
                                        }
                                    />
                                )}
                            </FormField>

                            <FormField
                                id="phone"
                                label={t('organization.fields.phone')}
                                hint={t('organization.fields.phone_hint')}
                                error={form.errors.phone}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="tel"
                                        value={form.data.phone}
                                        autoComplete="tel"
                                        maxLength={30}
                                        className="tabular-nums"
                                        onChange={(event) =>
                                            form.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </div>

                        <FormField
                            id="email"
                            label={t('organization.fields.email')}
                            error={form.errors.email}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    type="email"
                                    value={form.data.email}
                                    autoComplete="email"
                                    maxLength={255}
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
                            id="address"
                            label={t('organization.fields.address')}
                            error={form.errors.address}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    value={form.data.address}
                                    autoComplete="street-address"
                                    maxLength={500}
                                    onChange={(event) =>
                                        form.setData(
                                            'address',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>

                        {canUpdate && (
                            <div className="flex justify-end pt-1">
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={form.processing}
                                    className="w-full sm:w-auto"
                                >
                                    {form.processing && <Spinner />}
                                    {organization
                                        ? t('organization.actions.save')
                                        : t('organization.actions.create')}
                                </Button>
                            </div>
                        )}
                    </fieldset>
                </form>
            </div>
        </AppLayout>
    );
}
