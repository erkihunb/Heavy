import { Building2, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { organizations } from '@/routes/admin/permits';

/** An organization on record, as the lookup returns it (Admin\ApplicantController::organizations). */
export type OrganizationMatch = {
    id: number;
    name: string;
    email: string;
    organization: string;
    tin: string | null;
    phone: string | null;
};

/** The server answers from two characters on. */
const MIN_LENGTH = 2;

/**
 * Finds an organization already on record for a new request of theirs, by
 * the contact's name or e-mail, the organization's name, TIN or phone; once
 * one is chosen it shows who the request is for, with a way to change it.
 */
export function OrganizationLookup({
    value,
    onChange,
    error,
}: {
    value: OrganizationMatch | null;
    onChange: (match: OrganizationMatch | null) => void;
    error?: string;
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<{
        key: string;
        data: OrganizationMatch[];
    } | null>(null);
    const query = search.trim();
    const ready = query.length >= MIN_LENGTH;

    useEffect(() => {
        if (!ready) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(organizations.url({ query: { search: query } }), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) =>
                    response.ok ? response.json() : { data: [] },
                )
                .then((body: { data: OrganizationMatch[] }) =>
                    setResult({ key: query, data: body.data }),
                )
                .catch(() => {
                    // Aborted by the next keystroke, or offline.
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [ready, query]);

    if (value !== null) {
        return (
            <div className="space-y-2">
                <div className="flex flex-col gap-4 rounded-card border-2 border-primary bg-card p-4 sm:flex-row sm:items-start">
                    <Building2
                        aria-hidden="true"
                        className="size-5 shrink-0 text-primary"
                    />
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="text-xs text-muted-foreground">
                            {t('applicants.intake.chosen')}
                        </p>
                        <p className="font-semibold">{value.organization}</p>
                        <p className="text-sm">
                            {value.name} ·{' '}
                            <span translate="no">{value.email}</span>
                        </p>
                        {(value.tin || value.phone) && (
                            <p className="text-sm text-muted-foreground tabular-nums">
                                {[value.tin, value.phone]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        )}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onChange(null)}
                    >
                        {t('applicants.intake.change')}
                    </Button>
                </div>
                <InputError role="alert" message={error} />
            </div>
        );
    }

    const matches = ready && result?.key === query ? result.data : null;

    return (
        <div className="space-y-3">
            <FormField
                id="applicant_lookup"
                required
                label={t('applicants.intake.lookup')}
                hint={t('applicants.intake.lookup_hint')}
                error={error}
            >
                {(control) => (
                    <div className="relative">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            {...control}
                            type="search"
                            autoComplete="off"
                            className="pl-9"
                            maxLength={100}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>
                )}
            </FormField>

            <p role="status" aria-live="polite" className="sr-only">
                {matches === null
                    ? ''
                    : matches.length === 0
                      ? t('applicants.intake.lookup_empty')
                      : t('applicants.intake.lookup_found', {
                            count: String(matches.length),
                        })}
            </p>

            {ready && matches === null && (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Spinner />
                    {t('applicants.intake.lookup_searching')}
                </p>
            )}

            {matches !== null && matches.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('applicants.intake.lookup_empty')}
                </p>
            )}

            {matches !== null && matches.length > 0 && (
                <ul className="divide-y divide-border overflow-hidden rounded-card border border-border">
                    {matches.map((match) => (
                        <li key={match.id}>
                            <button
                                type="button"
                                onClick={() => onChange(match)}
                                className="flex w-full flex-col gap-0.5 px-4 py-3 text-left transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                            >
                                <span className="font-medium">
                                    {match.organization}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {[match.name, match.tin, match.phone]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
