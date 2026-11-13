import type { TruckVariant } from './scene';

type TruckProps = {
    variant: TruckVariant;
    /** Drawn smaller the further back it sits on the scene. */
    scale?: number;
};

/**
 * One heavy vehicle, flat and in a single colour, drawn around the origin so
 * whatever moves it only has to translate and rotate. Three silhouettes: a
 * cargo truck, a concrete mixer and a tractor-trailer.
 *
 * Decorative: the whole illustration is hidden from assistive technology by
 * its container, so nothing here is labelled.
 */
export function Truck({ variant, scale = 1 }: TruckProps) {
    return (
        <g transform={`scale(${scale})`}>
            {variant === 'trailer' && <Trailer />}
            {variant === 'cargo' && <Cargo />}
            {variant === 'mixer' && <Mixer />}
        </g>
    );
}

/* Wheels sit on y = 0, so every vehicle rides on the road line itself. */
function Wheels({ at }: { at: readonly number[] }) {
    return (
        <>
            {at.map((cx) => (
                <circle key={cx} cx={cx} cy={0} r={3.4} fill="currentColor" />
            ))}
        </>
    );
}

function Trailer() {
    return (
        <>
            <rect
                x={-34}
                y={-18}
                width={38}
                height={14}
                rx={1.5}
                fill="currentColor"
                opacity={0.62}
            />
            <rect
                x={6}
                y={-21}
                width={15}
                height={17}
                rx={2}
                fill="currentColor"
                opacity={0.9}
            />
            <rect
                x={13}
                y={-18}
                width={6}
                height={5}
                rx={1}
                fill="currentColor"
                opacity={0.35}
            />
            <Wheels at={[-28, -18, 15]} />
        </>
    );
}

function Cargo() {
    return (
        <>
            <rect
                x={-24}
                y={-17}
                width={27}
                height={13}
                rx={1.5}
                fill="currentColor"
                opacity={0.62}
            />
            <rect
                x={5}
                y={-19}
                width={13}
                height={15}
                rx={2}
                fill="currentColor"
                opacity={0.9}
            />
            <rect
                x={11}
                y={-16}
                width={5}
                height={4}
                rx={1}
                fill="currentColor"
                opacity={0.35}
            />
            <Wheels at={[-18, 12]} />
        </>
    );
}

function Mixer() {
    return (
        <>
            {/* The drum: an ellipse rather than a box, so it reads as a mixer. */}
            <ellipse
                cx={-12}
                cy={-13}
                rx={15}
                ry={9}
                fill="currentColor"
                opacity={0.62}
            />
            <rect
                x={2}
                y={-18}
                width={13}
                height={14}
                rx={2}
                fill="currentColor"
                opacity={0.9}
            />
            <rect
                x={8}
                y={-15}
                width={5}
                height={4}
                rx={1}
                fill="currentColor"
                opacity={0.35}
            />
            <Wheels at={[-16, 9]} />
        </>
    );
}
