import { useEffect, useMemo, useState } from 'react';
import type { OrganizationMatch } from '@/components/organization-lookup';
import type { IntakeFormData } from '@/components/permit-intake/form';
import { useTranslation } from '@/lib/i18n';
import {
    feeEstimate,
    organizationVehicles,
    organizations,
} from '@/routes/admin/permits';

/*
 * What makes the office form help as it is filled in: checks shown while
 * typing (the server still checks everything), an organization already on
 * record noticed before it is registered twice, a known vehicle's type
 * filled in from its plate, and the letter's fee added up.
 */

/** A plate as one comparable text: "region-code-number", code without leading zeros. */
export function plateKey(plate: string): string | null {
    const [region = '', code = '', ...rest] = plate.split('-');
    const number = rest
        .join('')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '');

    if (region === '' || code === '' || number.length < 4) {
        return null;
    }

    return `${region}-${String(Number(code))}-${number}`;
}

const PHONE = /^(?:\+?251|0)?[1-9]\d{8}$/;
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Mistakes visible before the form is sent, keyed like the server's errors:
 * a TIN that is not 10 digits, a phone or e-mail that cannot be right, a
 * letter dated in the future, a plate given twice. Empty fields are left to
 * the server, so nothing is flagged before it is typed.
 */
export function useLiveChecks(
    data: IntakeFormData,
    existingMode: boolean,
): Record<string, string> {
    const { t } = useTranslation();

    return useMemo(() => {
        const errors: Record<string, string> = {};

        if (!existingMode) {
            if (data.tin !== '' && !/^\d{10}$/.test(data.tin)) {
                errors.tin = t('applicants.intake.live.tin');
            }

            if (
                data.phone.trim() !== '' &&
                !PHONE.test(data.phone.replace(/[\s\-().]+/g, ''))
            ) {
                errors.phone = t('applicants.intake.live.phone');
            }

            if (
                data.contact_email.trim() !== '' &&
                !EMAIL.test(data.contact_email.trim())
            ) {
                errors.contact_email = t('applicants.intake.live.email');
            }
        }

        const today = todayIso();

        if (data.letter_date !== '' && data.letter_date > today) {
            errors.letter_date = t('applicants.intake.live.letter_future');
        }

        // Every plate of the form, by its field; one given twice is flagged where it repeats.
        const plates: [string, string][] = [
            ['vehicles.0.plate', data.plate],
            ['vehicles.1.plate', data.trailer_plate],
            ...data.more.flatMap((row, index): [string, string][] => [
                [`more.${index}.vehicles.0.plate`, row.plate],
                [`more.${index}.vehicles.1.plate`, row.trailer_plate],
            ]),
        ];
        const seen = new Set<string>();

        for (const [field, plate] of plates) {
            const key = plateKey(plate);

            if (key === null) {
                continue;
            }

            if (seen.has(key)) {
                errors[field] = t('applicants.intake.live.plate_twice');
            }

            seen.add(key);
        }

        return errors;
    }, [data, existingMode, t]);
}

/**
 * An organization already on record that the new one being typed is: the
 * same TIN or phone, or the same name. Asked of the lookup while typing.
 */
export function useKnownOrganization(
    data: IntakeFormData,
    existingMode: boolean,
): OrganizationMatch | null {
    const [found, setFound] = useState<{
        key: string;
        match: OrganizationMatch | null;
    } | null>(null);

    const phoneDigits = data.phone.replace(/\D/g, '').replace(/^(251|0)/, '');
    const name = data.name_am.trim();
    // The most telling thing typed so far: a full TIN, then a full phone, then the name.
    const search = existingMode
        ? ''
        : /^\d{10}$/.test(data.tin)
          ? data.tin
          : phoneDigits.length === 9
            ? phoneDigits
            : name.length >= 3
              ? name
              : '';

    useEffect(() => {
        if (search === '') {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(organizations.url({ query: { search } }), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) =>
                    response.ok ? response.json() : { data: [] },
                )
                .then((body: { data: OrganizationMatch[] }) => {
                    const same = body.data.find(
                        (match) =>
                            (match.tin !== null && match.tin === search) ||
                            (match.phone !== null &&
                                match.phone
                                    .replace(/\D/g, '')
                                    .endsWith(search)) ||
                            match.organization.trim().toLowerCase() ===
                                search.toLowerCase(),
                    );

                    setFound({ key: search, match: same ?? null });
                })
                .catch(() => {
                    // Aborted by the next keystroke, or offline.
                });
        }, 400);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [search]);

    return search !== '' && found?.key === search ? found.match : null;
}

/**
 * The vehicles the chosen organization already has, by plate key, so a
 * known vehicle's type is filled in when its plate is typed.
 */
export function useOrganizationVehicles(
    existing: OrganizationMatch | null,
): Map<string, string> {
    const [vehicles, setVehicles] = useState<{
        id: number;
        byPlate: Map<string, string>;
    } | null>(null);
    const id = existing?.id ?? null;

    useEffect(() => {
        if (id === null) {
            return;
        }

        const controller = new AbortController();

        fetch(organizationVehicles.url({ applicant: id }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : { data: [] }))
            .then(
                (body: {
                    data: { plate: string; vehicle_type_id: number }[];
                }) => {
                    const byPlate = new Map<string, string>();

                    for (const vehicle of body.data) {
                        const key = plateKey(vehicle.plate);

                        if (key !== null) {
                            byPlate.set(key, String(vehicle.vehicle_type_id));
                        }
                    }

                    setVehicles({ id, byPlate });
                },
            )
            .catch(() => {
                // Offline: the type is chosen by hand.
            });

        return () => controller.abort();
    }, [id]);

    return id !== null && vehicles?.id === id ? vehicles.byPlate : new Map();
}

export type FeeEstimate = {
    vehicles: number;
    /** Santim; null until every vehicle's type has a fee. */
    totalCents: number | null;
    /** Vehicle types with no fee for the letter's days. */
    missing: string[];
};

/**
 * The letter's fee: each vehicle at its own type's fee for the days of the
 * rate chosen, as approval will charge it (FeeCalculator::amountFor).
 */
export function useFeeEstimate(
    data: IntakeFormData,
    days: number | null,
): FeeEstimate {
    const types = [
        data.vehicle_type_id,
        ...data.more.map((row) => row.vehicle_type_id),
    ].filter((type) => type !== '');
    const distinct = [...new Set(types)].sort();
    const key =
        days === null || data.permit_type === '' || distinct.length === 0
            ? null
            : `${data.permit_type}|${days}|${distinct.join(',')}`;
    const [amounts, setAmounts] = useState<{
        key: string;
        byType: Record<string, number | null>;
    } | null>(null);

    useEffect(() => {
        if (key === null || days === null) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            const query = new URLSearchParams({
                permit_type: data.permit_type,
                days: String(days),
            });

            for (const type of distinct) {
                query.append('types[]', type);
            }

            fetch(`${feeEstimate.url()}?${query}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : null))
                .then(
                    (
                        body: {
                            amounts: Record<string, number | null>;
                        } | null,
                    ) => {
                        if (body !== null) {
                            setAmounts({ key, byType: body.amounts });
                        }
                    },
                )
                .catch(() => {
                    // Aborted or offline: the total is shown once it can be worked out.
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
        // `distinct` and the permit type are in `key`.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);

    if (key === null || amounts?.key !== key) {
        return { vehicles: types.length, totalCents: null, missing: [] };
    }

    const missing = distinct.filter((type) => amounts.byType[type] == null);

    return {
        vehicles: types.length,
        totalCents:
            missing.length > 0
                ? null
                : types.reduce(
                      (sum, type) => sum + (amounts.byType[type] ?? 0),
                      0,
                  ),
        missing,
    };
}

/** Today in the browser, as the date fields hold it (Y-m-d). */
export function todayIso(): string {
    const now = new Date();

    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 10);
}

/** A fee as the system prints it: "ETB 1,500.00". */
export function formatBirr(cents: number): string {
    return `ETB ${(cents / 100).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
