import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * The system's logo (EUISIS ApplicationLogo): the one an Admin uploaded in
 * System settings → General, otherwise the neutral placeholder mark below.
 * Decorative wherever it is used: the system name always sits next to it.
 */
export function BrandMark({ className }: { className?: string }) {
    const logo = usePage().props.branding?.logo ?? null;
    const [failed, setFailed] = useState<string | null>(null);

    if (logo && failed !== logo) {
        return (
            <img
                src={logo}
                alt=""
                aria-hidden="true"
                decoding="async"
                onError={() => setFailed(logo)}
                className={cn('object-contain', className)}
            />
        );
    }

    return <PlaceholderMark className={className} />;
}

/**
 * Neutral placeholder: a heavy vehicle on a sign plate. It is NOT the
 * Authority's logo, which must be supplied, never invented.
 */
function PlaceholderMark({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 64 64"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
            className={className}
        >
            <rect
                x="2"
                y="2"
                width="60"
                height="60"
                rx="14"
                fill="var(--highlight)"
                stroke="var(--asphalt)"
                strokeWidth="4"
            />
            <g fill="var(--asphalt)">
                <rect x="11" y="22" width="24" height="18" rx="2.5" />
                <path d="M37 27h8.5a3 3 0 0 1 2.5 1.3l4.3 6.2a3 3 0 0 1 .5 1.7V40H37z" />
                <circle cx="20" cy="43" r="5" />
                <circle cx="44" cy="43" r="5" />
            </g>
            <g fill="var(--highlight)">
                <circle cx="20" cy="43" r="2" />
                <circle cx="44" cy="43" r="2" />
            </g>
        </svg>
    );
}
