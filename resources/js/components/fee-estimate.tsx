import { useEffect, useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import type { Money } from '@/types';

type Estimate = { days: number; fee: Money | null };

/**
 * What the permit would cost by today's schedule, asked of the server once
 * the type, the vehicle and both dates are chosen. Purely informative: the fee
 * owed is fixed at approval. Announced politely to screen readers.
 */
export function FeeEstimate({
    permitType,
    primaryVehicleId,
    validFrom,
    validTo,
    url,
}: {
    permitType: string;
    primaryVehicleId: string;
    validFrom: string;
    validTo: string;
    /** The estimate address for the applicant the Registrar fills the form for. */
    url: string;
}) {
    const { t } = useTranslation();
    const [estimate, setEstimate] = useState<Estimate | null>(null);

    const complete =
        permitType !== '' &&
        primaryVehicleId !== '' &&
        validFrom !== '' &&
        validTo !== '' &&
        validFrom <= validTo;

    useEffect(() => {
        if (!complete) {
            return;
        }

        const controller = new AbortController();
        // Wait for the person to stop changing the selects.
        const timer = window.setTimeout(() => {
            const query = {
                permit_type: permitType,
                primary_vehicle_id: primaryVehicleId,
                valid_from: validFrom,
                valid_to: validTo,
            };

            fetch(`${url}?${new URLSearchParams(query).toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((body: Estimate | null) => setEstimate(body))
                .catch(() => {
                    // Aborted or offline: the estimate is optional.
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [complete, permitType, primaryVehicleId, validFrom, validTo, url]);

    if (!complete || estimate === null) {
        return null;
    }

    return (
        <div
            aria-live="polite"
            className="space-y-1 rounded-card border border-border bg-muted px-4 py-3"
        >
            <p className="text-eyebrow text-muted-foreground">
                {t('payments.fee_estimate')}
            </p>
            {estimate.fee ? (
                <p className="flex flex-wrap items-baseline gap-x-2">
                    <span className="text-xl font-extrabold tabular-nums">
                        {estimate.fee.formatted}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        {t('payments.fee_days', { days: estimate.days })}
                    </span>
                </p>
            ) : (
                <p className="font-medium">{t('payments.fee_unknown')}</p>
            )}
            <p className="text-sm text-muted-foreground">
                {t('payments.fee_estimate_hint')}
            </p>
        </div>
    );
}
