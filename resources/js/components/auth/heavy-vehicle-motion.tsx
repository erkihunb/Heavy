import type { CSSProperties, ReactNode } from 'react';

/**
 * The moving picture on the sign-in page: heavy vehicles crossing Addis
 * Ababa. It fills the brand panel's free space and leaves the centre, where
 * the Authority's mark and names are, alone.
 *
 * - Along the bottom: the Entoto ridge, the city's skyline with a tower
 *   crane and a dome, the light-rail viaduct with a train on it, and a
 *   two-lane road. Five different heavy vehicles use the road — a low-bed
 *   with a chained load, a tipper and a fuel tanker in the near lane, a
 *   concrete mixer and a container truck coming the other way in the far
 *   lane.
 * - Along the top: a few clouds drifting across the sky.
 *
 * Drawn here from nothing: inline SVG in the panel's own white, moved by CSS
 * keyframes on transform and opacity (the hv-* rules in resources/css/app.css).
 * Every vehicle carries its resting place as an inline transform, so without
 * those rules — reduced motion, or the Admin's "animations off" — the same
 * city is simply standing still, traffic stopped.
 */

/* Every moving thing travels this far in one loop (x = -260 → 1060). */
const TRAVEL = 1320;

/* The near lane is drawn full size; the far lane smaller and slower. */
const NEAR = { seconds: 27, scale: 1, ground: 240 };
const FAR = { seconds: 36, scale: 0.62, ground: 198 };

type Lane = typeof NEAR;

/** A tyre, a hub and spokes; it turns once per circumference travelled. */
function Wheel({ x, r = 13, speed }: { x: number; r?: number; speed: number }) {
    const spoke = r - 3;
    const spin = ((2 * Math.PI * r) / speed).toFixed(2);

    return (
        <g
            className="hv-wheel"
            style={{ '--hv-spin': `${spin}s` } as CSSProperties}
        >
            <circle cx={x} cy={-r} r={r} />
            <path
                d={`M${x - spoke} ${-r}H${x + spoke}M${x} ${-r - spoke}V${-r + spoke}`}
                strokeWidth="1.5"
                opacity="0.7"
            />
            <circle cx={x} cy={-r} r="2.5" fill="currentColor" stroke="none" />
        </g>
    );
}

/** The driver's cab, shared by every vehicle; x is its rear edge. */
function Cab({ x }: { x: number }) {
    return (
        <g transform={`translate(${x} 0)`}>
            <path d="M0 -24V-72A4 4 0 0 1 4 -76H34L48 -54V-24Z" />
            <path d="M12 -68H31L40 -54H12Z" strokeWidth="1.5" />
            <rect
                className="hv-beacon"
                x="13"
                y="-82"
                width="9"
                height="6"
                rx="1.5"
                fill="currentColor"
                stroke="none"
            />
        </g>
    );
}

/* Each vehicle is drawn facing right, standing on y = 0. */

function LowBed({ speed }: { speed: number }) {
    return (
        <>
            <g className="hv-body">
                <rect x="30" y="-84" width="118" height="52" rx="3" />
                <path
                    d="M60 -76V-40M90 -76V-40M120 -76V-40M30 -68L14 -32M148 -68L162 -32"
                    strokeWidth="1.5"
                    opacity="0.6"
                />
                <path d="M0 -32H162L176 -44H206V-38H179L165 -26H0Z" />
                <path d="M0 -32V-44H-9V-37H0" strokeWidth="1.5" />
                <path d="M178 -24H268" />
                <Cab x={214} />
            </g>
            <Wheel x={22} r={10} speed={speed} />
            <Wheel x={48} r={10} speed={speed} />
            <Wheel x={74} r={10} speed={speed} />
            <Wheel x={192} speed={speed} />
            <Wheel x={244} speed={speed} />
        </>
    );
}

function Tipper({ speed }: { speed: number }) {
    return (
        <>
            <g className="hv-body">
                <path d="M14 -72Q62 -96 112 -72" />
                <path d="M2 -30L8 -72H118V-30Z" />
                <path
                    d="M34 -64V-38M62 -64V-38M90 -64V-38"
                    strokeWidth="1.5"
                    opacity="0.6"
                />
                <path d="M0 -24H170" />
                <Cab x={122} />
            </g>
            <Wheel x={26} speed={speed} />
            <Wheel x={54} speed={speed} />
            <Wheel x={152} speed={speed} />
        </>
    );
}

function Tanker({ speed }: { speed: number }) {
    return (
        <>
            <g className="hv-body">
                <rect x="0" y="-80" width="198" height="46" rx="23" />
                <path
                    d="M50 -80V-34M99 -80V-34M148 -80V-34"
                    strokeWidth="1.5"
                    opacity="0.6"
                />
                <path d="M68 -80V-86H82V-80M116 -80V-86H130V-80" />
                <path d="M8 -28H196M178 -24H268" />
                <Cab x={214} />
            </g>
            <Wheel x={24} r={10} speed={speed} />
            <Wheel x={50} r={10} speed={speed} />
            <Wheel x={76} r={10} speed={speed} />
            <Wheel x={192} speed={speed} />
            <Wheel x={244} speed={speed} />
        </>
    );
}

function Mixer({ speed }: { speed: number }) {
    return (
        <>
            <g className="hv-body">
                <path d="M10 -44L30 -82H92L122 -58L112 -32H20Z" />
                <path
                    d="M50 -82L38 -32M72 -82L64 -32M94 -80L90 -32"
                    strokeWidth="1.5"
                    opacity="0.6"
                />
                <path d="M4 -86H20L28 -74" />
                <path d="M0 -24H178" />
                <Cab x={130} />
            </g>
            <Wheel x={30} speed={speed} />
            <Wheel x={58} speed={speed} />
            <Wheel x={160} speed={speed} />
        </>
    );
}

function ContainerTruck({ speed }: { speed: number }) {
    return (
        <>
            <g className="hv-body">
                <rect x="0" y="-86" width="204" height="52" rx="2" />
                <path
                    d="M17 -80V-40M34 -80V-40M51 -80V-40M68 -80V-40M85 -80V-40M102 -80V-40M119 -80V-40M136 -80V-40M153 -80V-40M170 -80V-40M187 -80V-40"
                    strokeWidth="1.5"
                    opacity="0.45"
                />
                <path d="M0 -30H206M186 -24H276" />
                <Cab x={222} />
            </g>
            <Wheel x={24} r={10} speed={speed} />
            <Wheel x={50} r={10} speed={speed} />
            <Wheel x={200} speed={speed} />
            <Wheel x={252} speed={speed} />
        </>
    );
}

/**
 * One vehicle in its lane. `rest` is where it stands when nothing moves;
 * `offset` (0–1) is how far round the loop it starts, so vehicles sharing a
 * lane keep their distance for ever.
 */
function Vehicle({
    lane,
    leftward = false,
    rest,
    offset,
    children,
}: {
    lane: Lane;
    leftward?: boolean;
    rest: number;
    offset: number;
    children: (speed: number) => ReactNode;
}) {
    const speed = TRAVEL / lane.seconds / lane.scale;

    return (
        <g transform={`translate(0 ${lane.ground})`}>
            <g
                className={leftward ? 'hv-left' : 'hv-right'}
                style={
                    {
                        '--hv-dur': `${lane.seconds}s`,
                        '--hv-delay': `${(-offset * lane.seconds).toFixed(1)}s`,
                        transform: `translateX(${rest}px)`,
                    } as CSSProperties
                }
            >
                <g
                    transform={`scale(${leftward ? -lane.scale : lane.scale} ${lane.scale})`}
                >
                    {children(speed)}
                </g>
            </g>
        </g>
    );
}

/* The skyline, left to right: [x, width, height], standing on y = 176. */
const BUILDINGS: [number, number, number][] = [
    [-30, 60, 70],
    [34, 44, 104],
    [82, 70, 56],
    [156, 38, 128],
    [198, 64, 82],
    [266, 54, 62],
    [324, 34, 142],
    [362, 76, 74],
    [442, 48, 110],
    [494, 66, 58],
    [564, 40, 132],
    [608, 72, 88],
    [684, 50, 66],
    [738, 44, 116],
    [786, 60, 78],
];

/** Rows of windows for one building, as a single dashed path. */
function windows([x, width, height]: [number, number, number]) {
    const rows: string[] = [];

    for (let y = 176 - height + 12; y < 168; y += 12) {
        rows.push(`M${x + 6} ${y}H${x + width - 6}`);
    }

    return rows.join('');
}

const CLOUD =
    'M12 0H84A12 12 0 0 0 86 -24A18 18 0 0 0 54 -34A22 22 0 0 0 14 -24A12 12 0 0 0 12 0Z';

/* The clouds: [height in the sky, size, seconds per crossing, start, rest]. */
const CLOUDS: [number, number, number, number, number][] = [
    [70, 1, 95, 0.15, 90],
    [128, 0.7, 120, 0.55, 430],
    [52, 0.85, 140, 0.8, 610],
];

const svgProps = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': true,
    focusable: false,
} as const;

export function HeavyVehicleMotion() {
    return (
        <>
            {/* The sky. */}
            <svg
                {...svgProps}
                viewBox="0 0 800 220"
                preserveAspectRatio="xMidYMin slice"
                className="pointer-events-none absolute inset-x-0 top-0 -z-10 hidden h-[28%] w-full lg:block"
            >
                {CLOUDS.map(([y, size, seconds, offset, rest]) => (
                    <g key={y} transform={`translate(0 ${y})`} opacity="0.3">
                        <g
                            className="hv-right"
                            style={
                                {
                                    '--hv-dur': `${seconds}s`,
                                    '--hv-delay': `${(-offset * seconds).toFixed(1)}s`,
                                    transform: `translateX(${rest}px)`,
                                } as CSSProperties
                            }
                        >
                            <path d={CLOUD} transform={`scale(${size})`} />
                        </g>
                    </g>
                ))}
            </svg>

            {/* The city and its road. */}
            <svg
                {...svgProps}
                viewBox="0 0 800 260"
                preserveAspectRatio="xMidYMax slice"
                data-test="heavy-vehicle-motion"
                className="pointer-events-none absolute inset-x-0 bottom-0 -z-10 hidden h-[36%] max-h-80 w-full lg:block"
            >
                {/* The Entoto ridge behind everything. */}
                <path
                    d="M-20 100Q60 44 150 74T330 60T520 80T700 52T830 84"
                    opacity="0.3"
                />
                <path
                    d="M-20 120Q110 84 230 104T470 96T830 112"
                    strokeWidth="1.5"
                    opacity="0.2"
                />

                {/* The skyline: towers, a crane on a site, and a dome. */}
                <g fill="var(--brand-surface)">
                    <g opacity="0.55">
                        <path d="M420 176V22M396 28H474M408 28V40H400M462 28V58M420 22L396 28M420 22L474 28" />
                    </g>
                    {BUILDINGS.map((building) => {
                        const [x, width, height] = building;

                        return (
                            <g key={x}>
                                <rect
                                    x={x}
                                    y={176 - height}
                                    width={width}
                                    height={height}
                                    strokeOpacity="0.55"
                                    strokeWidth="1.5"
                                />
                                <path
                                    d={windows(building)}
                                    strokeWidth="1.5"
                                    strokeDasharray="4 5"
                                    opacity="0.3"
                                />
                                {height > 120 && (
                                    <path
                                        d={`M${x + width / 2} ${176 - height}V${158 - height}`}
                                        strokeWidth="1.5"
                                        opacity="0.55"
                                    />
                                )}
                            </g>
                        );
                    })}
                    <path
                        d="M494 176A40 32 0 0 1 574 176ZM534 144V134"
                        strokeOpacity="0.55"
                        strokeWidth="1.5"
                    />

                    {/* The light-rail viaduct, and the train that uses it. */}
                    <path
                        d="M30 154V176M110 154V176M190 154V176M270 154V176M350 154V176M430 154V176M510 154V176M590 154V176M670 154V176M750 154V176"
                        strokeOpacity="0.5"
                    />
                    <g transform="translate(0 150)">
                        <g
                            className="hv-left"
                            style={
                                {
                                    '--hv-dur': '44s',
                                    '--hv-delay': '-12s',
                                    transform: 'translateX(470px)',
                                } as CSSProperties
                            }
                        >
                            <rect x="0" y="-16" width="54" height="16" rx="4" />
                            <rect
                                x="58"
                                y="-16"
                                width="54"
                                height="16"
                                rx="4"
                            />
                            <rect
                                x="116"
                                y="-16"
                                width="54"
                                height="16"
                                rx="4"
                            />
                            <path
                                d="M7 -9H47M65 -9H105M123 -9H163"
                                strokeWidth="1.5"
                                strokeDasharray="6 4"
                                opacity="0.6"
                            />
                        </g>
                    </g>
                    <rect
                        x="-20"
                        y="150"
                        width="840"
                        height="5"
                        strokeOpacity="0.7"
                        strokeWidth="1.5"
                    />

                    {/* The road: kerb, lane markings and the near edge. */}
                    <rect
                        x="-20"
                        y="176"
                        width="840"
                        height="90"
                        stroke="none"
                    />
                    <path d="M-20 176H820" opacity="0.6" />
                    <path
                        d="M-20 212H820"
                        strokeDasharray="22 16"
                        opacity="0.4"
                    />
                    <path d="M-20 252H820" opacity="0.45" />

                    {/* The far lane, heading out of the city. */}
                    <Vehicle lane={FAR} leftward rest={330} offset={0.1}>
                        {(speed) => <Mixer speed={speed} />}
                    </Vehicle>
                    <Vehicle lane={FAR} leftward rest={640} offset={0.6}>
                        {(speed) => <ContainerTruck speed={speed} />}
                    </Vehicle>

                    {/* The near lane, heading in. */}
                    <Vehicle lane={NEAR} rest={130} offset={0}>
                        {(speed) => <Tipper speed={speed} />}
                    </Vehicle>
                    <Vehicle lane={NEAR} rest={360} offset={0.34}>
                        {(speed) => <LowBed speed={speed} />}
                    </Vehicle>
                    <Vehicle lane={NEAR} rest={700} offset={0.67}>
                        {(speed) => <Tanker speed={speed} />}
                    </Vehicle>
                </g>
            </svg>
        </>
    );
}
