import type { SVGAttributes } from 'react';

/**
 * Neutral placeholder mark: a heavy vehicle on a sign plate.
 *
 * It is NOT the Authority's logo. The official logo is an open question
 * (docs/DECISIONS.md) and must be supplied, never invented.
 */
export function BrandMark(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 64 64"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
            {...props}
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
