import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EthiopianDateInput } from '@/components/ethiopian-date-input';
import { FormSection } from '@/components/form-layout';
import PageHeader from '@/shell/Components/PageHeader';
import {
    Alert,
    Button,
    FieldError,
    FormField,
    Input,
    badgeClassName,
    buttonClassName,
    cx,
} from '@/shell/Components/ui';
import { useLocale } from '@/shell/hooks/useLocale';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';
import type { InstitutionVehicle } from './index';

/** "2026-11-17" plus a number of days, as the same kind of string. */
function addDays(date: string, days: number): string {
    const value = new Date(`${date}T00:00:00Z`);
    value.setUTCDate(value.getUTCDate() + days);

    return value.toISOString().slice(0, 10);
}

/** The days a period covers, both ends counted; 0 when it is not a period. */
function daysBetween(from: string, to: string): number {
    const start = Date.parse(`${from}T00:00:00Z`);
    const end = Date.parse(`${to}T00:00:00Z`);

    return Number.isNaN(start) || Number.isNaN(end) || end < start
        ? 0
        : Math.round((end - start) / 86_400_000) + 1;
}

/**
 * Giving institution vehicles their permits: choose the vehicles, the
 * period, what they are used for and, if wanted, the route. Each chosen
 * vehicle gets its own permit, in force at once; a vehicle that already
 * holds one is shown as such and cannot be chosen. Opened from a vehicle's
 * row, that vehicle is already chosen.
 */
export default function GiveInstitutionPermits({
    vehicles,
    preselected,
    today,
    maxDays,
    urls,
}: {
    vehicles: InstitutionVehicle[];
    preselected: string[];
    today: string;
    maxDays: number;
    urls: {
        index: string;
        store: string;
        vehicles: string;
        registerVehicle: string;
    };
}) {
    const { t } = useLocale();
    const form = useForm<{
        vehicles: string[];
        valid_from: string;
        valid_to: string;
        reason: string;
        route: string;
    }>({
        vehicles: preselected,
        valid_from: today,
        valid_to: addDays(today, 29),
        reason: '',
        route: '',
    });
    const [query, setQuery] = useState('');

    const free = vehicles.filter((vehicle) => vehicle.permit === null);
    const needle = query.trim().toLowerCase();
    const shown =
        needle === ''
            ? vehicles
            : vehicles.filter((vehicle) =>
                  [vehicle.plate, vehicle.type]
                      .join(' ')
                      .toLowerCase()
                      .includes(needle),
              );
    const count = form.data.vehicles.length;
    const days = daysBetween(form.data.valid_from, form.data.valid_to);
    const [one, many] = t('institution.issue_submit').split('|');

    const presets: { label: string; days: number }[] = [
        {
            label: t('institution.preset_days').replace(':count', '30'),
            days: 30,
        },
        {
            label: t('institution.preset_months').replace(':count', '3'),
            days: 90,
        },
        {
            label: t('institution.preset_months').replace(':count', '6'),
            days: 180,
        },
        { label: t('institution.preset_year'), days: Math.min(365, maxDays) },
    ];

    function toggle(id: string) {
        form.setData(
            'vehicles',
            form.data.vehicles.includes(id)
                ? form.data.vehicles.filter((item) => item !== id)
                : [...form.data.vehicles, id],
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(urls.store, { preserveScroll: true });
    }

    const vehicleError =
        form.errors.vehicles ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('vehicles.'),
        )?.[1];

    return (
        <AuthenticatedLayout
            contentWidth="form"
            header={
                <PageHeader
                    backHref={urls.index}
                    title={t('institution.issue_title')}
                    description={t('institution.issue_intro')}
                />
            }
        >
            <Head title={t('institution.issue_title')} />

            <form onSubmit={submit} noValidate className="space-y-5">
                <FormSection
                    step={1}
                    required
                    title={t('institution.fields.vehicles')}
                    aside={
                        <>
                            <span
                                aria-live="polite"
                                className={cx(
                                    badgeClassName(
                                        count > 0 ? 'success' : 'neutral',
                                    ),
                                )}
                            >
                                {t('institution.selected').replace(
                                    ':count',
                                    String(count),
                                )}
                            </span>
                            {free.length > 1 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        form.setData(
                                            'vehicles',
                                            count === free.length
                                                ? []
                                                : free.map((item) => item.id),
                                        )
                                    }
                                >
                                    {count === free.length
                                        ? t('rbac.users.clearSelection')
                                        : t('institution.select_all')}
                                </Button>
                            )}
                        </>
                    }
                >
                    {vehicles.length === 0 ? (
                        <Alert tone="info">
                            {t('institution.no_vehicles_for_permit')}{' '}
                            <Link
                                href={urls.registerVehicle}
                                className="font-medium underline underline-offset-2"
                            >
                                {t('institution.register_vehicle')}
                            </Link>
                        </Alert>
                    ) : (
                        <>
                            {free.length === 0 && (
                                <Alert tone="warning">
                                    {t('institution.all_hold_permits')}
                                </Alert>
                            )}
                            {vehicles.length > 6 && (
                                <Input
                                    type="search"
                                    aria-label={t(
                                        'institution.search_vehicles',
                                    )}
                                    placeholder={t(
                                        'institution.search_vehicles',
                                    )}
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                />
                            )}
                            {shown.length === 0 ? (
                                <p className="text-sm text-(--app-muted-foreground)">
                                    {t('institution.no_vehicle_matches')}
                                </p>
                            ) : (
                                <ul className="grid max-h-96 gap-2 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">
                                    {shown.map((vehicle) => {
                                        const checked =
                                            form.data.vehicles.includes(
                                                vehicle.id,
                                            );
                                        const held = vehicle.permit !== null;

                                        return (
                                            <li key={vehicle.id}>
                                                <label
                                                    className={cx(
                                                        'flex min-h-14 items-center gap-3 rounded-control border px-3 py-2.5 transition-colors',
                                                        held
                                                            ? 'cursor-not-allowed bg-(--app-surface-muted) opacity-70'
                                                            : 'cursor-pointer',
                                                        checked
                                                            ? 'border-primary bg-primary/8'
                                                            : 'border-(--app-border)',
                                                        !held &&
                                                            !checked &&
                                                            'hover:bg-(--app-surface-muted)',
                                                    )}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        className="size-4 shrink-0 rounded border-(--app-border-strong) accent-primary"
                                                        checked={checked}
                                                        disabled={held}
                                                        onChange={() =>
                                                            toggle(vehicle.id)
                                                        }
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block font-mono text-sm font-semibold text-(--app-foreground)">
                                                            {vehicle.plate}
                                                        </span>
                                                        <span className="block truncate text-xs text-(--app-muted-foreground)">
                                                            {held
                                                                ? t(
                                                                      'institution.already_holds',
                                                                  )
                                                                      .replace(
                                                                          ':number',
                                                                          vehicle
                                                                              .permit
                                                                              ?.number ??
                                                                              '',
                                                                      )
                                                                      .replace(
                                                                          ':date',
                                                                          vehicle
                                                                              .permit
                                                                              ?.valid_to
                                                                              ?.ethiopian_label ??
                                                                              '',
                                                                      )
                                                                : vehicle.type}
                                                        </span>
                                                    </span>
                                                </label>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </>
                    )}
                    <FieldError>{vehicleError}</FieldError>
                </FormSection>

                <FormSection
                    step={2}
                    title={t('institution.columns.period')}
                    aside={
                        <span className={badgeClassName('success')}>
                            {t('institution.free')}
                        </span>
                    }
                >
                    <div
                        role="group"
                        aria-label={t('institution.presets')}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <span className="text-xs text-(--app-muted-foreground)">
                            {t('institution.presets')}
                        </span>
                        {presets.map((preset) => {
                            const active = days === preset.days;

                            return (
                                <button
                                    key={preset.label}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() =>
                                        form.setData(
                                            'valid_to',
                                            addDays(
                                                form.data.valid_from || today,
                                                preset.days - 1,
                                            ),
                                        )
                                    }
                                    className={cx(
                                        'inline-flex min-h-8 items-center rounded-full border px-3 text-xs font-medium transition-colors',
                                        active
                                            ? 'border-primary bg-primary text-(--color-on-primary,#fff)'
                                            : 'border-(--app-border) text-(--app-foreground) hover:bg-(--app-surface-muted)',
                                    )}
                                >
                                    {preset.label}
                                </button>
                            );
                        })}
                    </div>
                    {/* Chosen on the Ethiopian calendar like every date in the system; the value is still a Gregorian ISO date. */}
                    <div className="grid items-start gap-4 sm:grid-cols-2">
                        <EthiopianDateInput
                            id="valid_from"
                            label={t('institution.fields.valid_from')}
                            required
                            error={form.errors.valid_from}
                            min={today}
                            yearsAfter={2}
                            value={form.data.valid_from}
                            onChange={(iso) => form.setData('valid_from', iso)}
                        />
                        <EthiopianDateInput
                            id="valid_to"
                            label={t('institution.fields.valid_to')}
                            required
                            error={form.errors.valid_to}
                            min={form.data.valid_from || today}
                            max={addDays(
                                form.data.valid_from || today,
                                maxDays - 1,
                            )}
                            yearsAfter={2}
                            value={form.data.valid_to}
                            onChange={(iso) => form.setData('valid_to', iso)}
                        />
                    </div>
                    <p className="text-xs text-(--app-muted-foreground)">
                        {days > 0 && (
                            <strong className="font-semibold text-(--app-foreground)">
                                {t('institution.days_total').replace(
                                    ':count',
                                    String(days),
                                )}
                                {'. '}
                            </strong>
                        )}
                        {t('institution.fields.period_help').replace(
                            ':max',
                            String(maxDays),
                        )}
                    </p>
                    <div className="grid items-start gap-5 md:grid-cols-2">
                        <FormField
                            label={t('institution.fields.reason')}
                            required
                            error={form.errors.reason}
                        >
                            {({ id, describedBy, invalid }) => (
                                <Input
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    maxLength={500}
                                    placeholder={t(
                                        'institution.fields.reason_placeholder',
                                    )}
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>
                        <FormField
                            label={t('institution.fields.route')}
                            error={form.errors.route}
                            description={t('institution.fields.route_help')}
                        >
                            {({ id, describedBy, invalid }) => (
                                <Input
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    maxLength={255}
                                    placeholder={t(
                                        'institution.fields.route_placeholder',
                                    )}
                                    value={form.data.route}
                                    onChange={(event) =>
                                        form.setData(
                                            'route',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>
                    </div>
                </FormSection>

                <div className="flex justify-end gap-2">
                    <Link
                        href={urls.index}
                        className={buttonClassName({ variant: 'ghost' })}
                    >
                        {t('shell.common.cancel')}
                    </Link>
                    <Button
                        type="submit"
                        loading={form.processing}
                        disabled={count === 0 || form.data.reason.trim() === ''}
                    >
                        {count > 1
                            ? (many ?? one).replace(':count', String(count))
                            : one}
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
