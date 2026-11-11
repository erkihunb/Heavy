import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, UserPlus, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { create, index, store } from '@/routes/admin/applicants';
import type { BreadcrumbItem } from '@/types';

type Props = {
    /** Supported languages: code => name written in that language. */
    locales: Record<string, string>;
};

/**
 * Register an applicant who came to the office: the contact person, who
 * gets an e-mail to set a password, and their organization.
 */
export default function ApplicantsCreate({ locales }: Props) {
    const { t, locale } = useTranslation();
    const form = useForm({
        contact_name: '',
        contact_email: '',
        locale,
        name_am: '',
        name_en: '',
        tin: '',
        phone: '',
        email: '',
        address: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const text = (
        field: 'contact_name' | 'name_am' | 'name_en' | 'address',
        label: string,
        extra: {
            lang?: string;
            maxLength?: number;
            autoComplete?: string;
        } = {},
    ) => (
        <FormField id={field} label={label} error={form.errors[field]}>
            {(control) => (
                <Input
                    {...control}
                    {...extra}
                    value={form.data[field]}
                    onChange={(event) =>
                        form.setData(field, event.target.value)
                    }
                />
            )}
        </FormField>
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('applicants.title'), href: index() },
        { title: t('applicants.create_title'), href: create() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('applicants.create_title')} />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    title={t('applicants.create_title')}
                    description={t('applicants.create_intro')}
                />

                <form
                    onSubmit={submit}
                    noValidate
                    className="flex max-w-3xl flex-col gap-6"
                >
                    <SectionCard
                        id="contact"
                        icon={UserRound}
                        title={t('applicants.contact_title')}
                        description={t('applicants.contact_intro')}
                    >
                        <div className="space-y-5">
                            {text(
                                'contact_name',
                                t('applicants.fields.contact_name'),
                                { maxLength: 255, autoComplete: 'off' },
                            )}
                            <div className="grid gap-5 sm:grid-cols-[1fr_12rem]">
                                <FormField
                                    id="contact_email"
                                    label={t('applicants.fields.contact_email')}
                                    hint={t(
                                        'applicants.fields.contact_email_hint',
                                    )}
                                    error={form.errors.contact_email}
                                >
                                    {(control) => (
                                        <Input
                                            {...control}
                                            type="email"
                                            autoComplete="off"
                                            translate="no"
                                            maxLength={255}
                                            value={form.data.contact_email}
                                            onChange={(event) =>
                                                form.setData(
                                                    'contact_email',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="locale"
                                    label={t('applicants.fields.locale')}
                                    error={form.errors.locale}
                                >
                                    {(control) => (
                                        <NativeSelect
                                            {...control}
                                            value={form.data.locale}
                                            onChange={(event) =>
                                                form.setData(
                                                    'locale',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {Object.entries(locales).map(
                                                ([code, name]) => (
                                                    <option
                                                        key={code}
                                                        value={code}
                                                    >
                                                        {name}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard
                        id="organization"
                        icon={Building2}
                        title={t('applicants.organization')}
                        description={t('applicants.organization_intro')}
                    >
                        <div className="space-y-5">
                            {text('name_am', t('organization.fields.name_am'), {
                                lang: 'am',
                                maxLength: 255,
                            })}
                            {text('name_en', t('organization.fields.name_en'), {
                                lang: 'en',
                                maxLength: 255,
                            })}
                            <div className="grid gap-5 sm:grid-cols-2">
                                <FormField
                                    id="tin"
                                    label={t('organization.fields.tin')}
                                    hint={t('organization.fields.tin_hint')}
                                    error={form.errors.tin}
                                >
                                    {(control) => (
                                        <Input
                                            {...control}
                                            inputMode="numeric"
                                            className="tabular-nums"
                                            value={form.data.tin}
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
                                            maxLength={30}
                                            className="tabular-nums"
                                            value={form.data.phone}
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
                                        translate="no"
                                        maxLength={255}
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
                            {text('address', t('organization.fields.address'), {
                                maxLength: 500,
                            })}
                        </div>
                    </SectionCard>

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
                            {t('applicants.register')}
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
