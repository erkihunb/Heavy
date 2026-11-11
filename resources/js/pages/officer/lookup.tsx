import { Head, useForm } from '@inertiajs/react';
import { ScanLine, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { verify } from '@/routes/officer';
import { lookup } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

/**
 * The traffic officer's manual check: permit number and a plate. Scanning
 * the QR code with the phone camera opens the same result directly.
 */
export default function OfficerLookup() {
    const { t } = useTranslation();
    const form = useForm({ permit_number: '', plate: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(lookup.url());
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('verification.officer.nav'), href: verify() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('verification.officer.nav')} />

            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={t('verification.lookup.title')}
                    description={t('verification.officer.lookup_intro')}
                />

                <p className="flex items-center gap-3 rounded-card border border-border bg-muted px-4 py-3 text-sm font-medium">
                    <ScanLine aria-hidden="true" className="size-5 shrink-0" />
                    {t('verification.lookup.intro')}
                </p>

                <form
                    onSubmit={submit}
                    noValidate
                    className="space-y-5 rounded-card border border-border bg-card p-5"
                >
                    <FormField
                        id="permit_number"
                        label={t('verification.fields.permit_number')}
                        error={form.errors.permit_number}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                value={form.data.permit_number}
                                autoComplete="off"
                                autoCapitalize="characters"
                                spellCheck={false}
                                translate="no"
                                placeholder="TMA-HV-2018-000123"
                                className="h-12 text-lg tabular-nums"
                                onChange={(event) =>
                                    form.setData(
                                        'permit_number',
                                        event.target.value,
                                    )
                                }
                            />
                        )}
                    </FormField>

                    <FormField
                        id="plate"
                        label={t('verification.fields.plate')}
                        error={form.errors.plate}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                value={form.data.plate}
                                autoComplete="off"
                                autoCapitalize="characters"
                                spellCheck={false}
                                translate="no"
                                placeholder="አአ-03-A45301"
                                className="h-12 text-lg tabular-nums"
                                onChange={(event) =>
                                    form.setData('plate', event.target.value)
                                }
                            />
                        )}
                    </FormField>

                    <Button
                        type="submit"
                        size="lg"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <Search aria-hidden="true" />
                        )}
                        {t('verification.lookup.submit')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
