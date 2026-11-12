import { Head, router, useForm } from '@inertiajs/react';
import { QrCode, Search, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { QrScanner } from '@/components/qr-scanner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { permitToken } from '@/lib/permit-qr';
import { verify } from '@/routes/officer';
import { lookup } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

/**
 * The traffic officer's check (spec §7): scan the permit's QR code with the
 * device camera, which opens the officer view of that permit, or type the
 * permit number and a plate.
 */
export default function OfficerLookup({ verifyBase }: { verifyBase: string }) {
    const { t } = useTranslation();
    const form = useForm({ permit_number: '', plate: '' });
    const [scanning, setScanning] = useState(false);
    const [foreign, setForeign] = useState(false);

    const scanned = (text: string): boolean => {
        const token = permitToken(text, verifyBase, window.location.origin);

        if (token === null) {
            setForeign(true);

            return false;
        }

        setForeign(false);
        setScanning(false);
        router.visit(`/v/${token}`);

        return true;
    };

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

                {scanning ? (
                    <QrScanner
                        onResult={scanned}
                        onClose={() => {
                            setScanning(false);
                            setForeign(false);
                        }}
                    />
                ) : (
                    <Button
                        type="button"
                        size="lg"
                        className="h-16 w-full text-base"
                        onClick={() => setScanning(true)}
                    >
                        <QrCode aria-hidden="true" className="size-6" />
                        {t('verification.officer.scanner.start')}
                    </Button>
                )}

                {foreign && (
                    <p
                        role="alert"
                        className="flex items-center gap-2 rounded-card border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
                    >
                        <TriangleAlert
                            aria-hidden="true"
                            className="size-4 shrink-0"
                        />
                        {t('verification.officer.scanner.not_permit')}
                    </p>
                )}

                <p className="flex items-center gap-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    <span
                        aria-hidden="true"
                        className="h-px flex-1 bg-border"
                    />
                    {t('verification.officer.scanner.or')}
                    <span
                        aria-hidden="true"
                        className="h-px flex-1 bg-border"
                    />
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
                                placeholder="AACRA-2018-000123"
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
