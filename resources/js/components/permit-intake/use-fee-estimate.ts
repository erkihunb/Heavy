import { useEffect, useState } from 'react';
import { filled } from '@/components/permit-intake/form';
import { intakeFee } from '@/routes/admin/permits';
import type { Money } from '@/types';

/**
 * What the request would cost by today's schedule, asked of the server once
 * the type, the vehicle type and both dates are chosen. Shown for the
 * applicant's information: the fee is paid only once the permit is approved.
 */
export function useFeeEstimate(
    permitType: string,
    vehicleTypeId: string,
    validFrom: string,
    validTo: string,
): { days: number; fee: Money | null } | null {
    const [estimate, setEstimate] = useState<{
        key: string;
        days: number;
        fee: Money | null;
    } | null>(null);
    const complete =
        filled(permitType, vehicleTypeId, validFrom, validTo) &&
        validFrom <= validTo;
    const key = [permitType, vehicleTypeId, validFrom, validTo].join('|');

    useEffect(() => {
        if (!complete) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(
                intakeFee.url({
                    query: {
                        permit_type: permitType,
                        vehicle_type_id: vehicleTypeId,
                        valid_from: validFrom,
                        valid_to: validTo,
                    },
                }),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            )
                .then((response) => (response.ok ? response.json() : null))
                .then((body: { days: number; fee: Money | null } | null) =>
                    setEstimate(body === null ? null : { key, ...body }),
                )
                .catch(() => {
                    // Aborted or offline: the estimate only helps.
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [complete, key, permitType, vehicleTypeId, validFrom, validTo]);

    return complete && estimate?.key === key ? estimate : null;
}
