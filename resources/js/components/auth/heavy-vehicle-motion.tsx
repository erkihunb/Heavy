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
 * Three things in it are the system itself rather than scenery:
 *
 * - The restricted hours. The scene has a day and a night: the sun crosses
 *   and sets, the panel dims, windows and headlights come on, the moon and
 *   stars take over, and the heavy vehicles keep moving through all of it.
 * - The permit. Each vehicle in the near lane is one that holds a permit.
 * - The roadside check. A traffic officer stands at the kerb, scanner at
 *   their side; as each vehicle comes up they raise it, and as the cab
 *   passes the scanner flashes, the permit's QR code shows over
 *   that vehicle with the scan line crossing it, and the code then gives way
 *   to the answer, which travels on with the vehicle: a tick for a permit in
 *   force, or a red cross for one that is refused (expired, revoked, not
 *   found). One of the three vehicles is refused.
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

/* Where the traffic officer stands, and so where a passing cab is checked. */
const OFFICER_X = 385;

/**
 * The (negative) delay that starts a per-vehicle loop at the moment that
 * vehicle's cab reaches the officer.
 */
function checkDelay(lane: Lane, offset: number, cab: number) {
    const reached = (OFFICER_X - cab + 260) / TRAVEL;

    return (reached - offset - 1) * lane.seconds;
}

/* The near lane: what drives there, where it rests, how far round the loop
   it starts, where along its length the cab is, and whether the officer's
   scan refuses it. */
const NEAR_TRAFFIC = [
    { kind: 'tipper', rest: 130, offset: 0, cab: 146, refused: false },
    { kind: 'lowbed', rest: 420, offset: 0.34, cab: 238, refused: true },
    { kind: 'tanker', rest: 720, offset: 0.67, cab: 238, refused: false },
] as const;

/* The colour of a refused scan; the tick keeps the panel's own white. */
const REFUSED = '#ef4444';

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
            <path
                className="hv-night"
                d="M48 -40L96 -30V-20L48 -32Z"
                fill="currentColor"
                fillOpacity="0.3"
                stroke="none"
            />
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
 * lane keep their distance for ever. `cab` marks a vehicle the officer
 * checks: it gets the answer that appears as its cab passes them, a tick
 * unless the scan is `refused`.
 */
function Vehicle({
    lane,
    leftward = false,
    rest,
    offset,
    cab,
    refused = false,
    children,
}: {
    lane: Lane;
    leftward?: boolean;
    rest: number;
    offset: number;
    cab?: number;
    refused?: boolean;
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
                {cab !== undefined && (
                    <g
                        className="hv-qr"
                        style={
                            {
                                '--hv-dur': `${lane.seconds}s`,
                                '--hv-delay': `${checkDelay(lane, offset, cab).toFixed(1)}s`,
                            } as CSSProperties
                        }
                    >
                        <g transform={`translate(${cab - 13} -120)`}>
                            <rect
                                width="26"
                                height="26"
                                rx="3"
                                fill="currentColor"
                                stroke="none"
                            />
                            <path
                                d="M3 3h6v6h-6zM17 3h6v6h-6zM3 17h6v6h-6zM11 4h2v2h-2zM12 9h3v2h-3zM4 11h2v3h-2zM9 12h2v2h-2zM14 13h3v2h-3zM20 11h2v2h-2zM11 17h2v3h-2zM16 17h2v2h-2zM20 16h3v2h-3zM14 21h2v2h-2zM19 20h2v3h-2z"
                                fill="var(--brand-surface)"
                                stroke="none"
                            />
                            <path
                                d="M5 5h2v2h-2zM19 5h2v2h-2zM5 19h2v2h-2z"
                                fill="currentColor"
                                stroke="none"
                            />
                            <path
                                className="hv-qr-line"
                                d="M-4 3H30"
                                stroke="var(--brand-accent, currentColor)"
                                strokeWidth="2"
                            />
                        </g>
                    </g>
                )}
                {cab !== undefined && (
                    <g
                        className="hv-verified"
                        style={
                            {
                                '--hv-dur': `${lane.seconds}s`,
                                '--hv-delay': `${checkDelay(lane, offset, cab).toFixed(1)}s`,
                            } as CSSProperties
                        }
                    >
                        <circle
                            cx={cab}
                            cy="-102"
                            r="11"
                            fill={refused ? REFUSED : 'currentColor'}
                            stroke={refused ? 'currentColor' : 'none'}
                            strokeWidth="1.5"
                        />
                        {refused ? (
                            <path
                                d={`M${cab - 4.5} -106.5l9 9M${cab + 4.5} -106.5l-9 9`}
                                stroke="currentColor"
                                strokeWidth="2.5"
                            />
                        ) : (
                            <path
                                d={`M${cab - 5.5} -102l4 4 7.5 -8`}
                                stroke="var(--brand-surface)"
                                strokeWidth="2.5"
                            />
                        )}
                    </g>
                )}
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
            {/* Night falling over the whole panel. */}
            <div
                aria-hidden="true"
                className="hv-dusk pointer-events-none absolute inset-0 -z-10 hidden bg-black lg:block"
            />

            {/* The sky. */}
            <svg
                {...svgProps}
                viewBox="0 0 800 220"
                preserveAspectRatio="xMidYMin slice"
                className="pointer-events-none absolute inset-x-0 top-0 -z-10 hidden h-[28%] w-full lg:block"
            >
                {/* The sun by day; the moon and a few stars by night. */}
                <g className="hv-sun" opacity="0.8">
                    <circle cx="400" cy="92" r="15" />
                    <path
                        d="M400 66V58M400 126V118M374 92H366M434 92H426M381.6 73.6L376 68M424 116L418.4 110.4M381.6 110.4L376 116M424 68L418.4 73.6"
                        strokeWidth="1.5"
                    />
                </g>
                <g className="hv-sun hv-moon" opacity="0.8">
                    <path d="M404 76A17 17 0 1 0 418 102A13 13 0 1 1 404 76Z" />
                </g>
                <g className="hv-night" fill="currentColor" stroke="none">
                    <circle cx="130" cy="40" r="1.6" />
                    <circle cx="238" cy="96" r="1.3" />
                    <circle cx="322" cy="30" r="1.8" />
                    <circle cx="468" cy="52" r="1.3" />
                    <circle cx="556" cy="24" r="1.6" />
                    <circle cx="648" cy="88" r="1.4" />
                    <circle cx="712" cy="38" r="1.7" />
                    <circle cx="60" cy="112" r="1.3" />
                </g>

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
                                    className="hv-windows"
                                    d={windows(building)}
                                    strokeWidth="1.5"
                                    strokeDasharray="4 5"
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

                    {/* The near lane, heading in: the vehicles that are
                        checked as they pass the officer. */}
                    {NEAR_TRAFFIC.map((truck) => (
                        <Vehicle
                            key={truck.kind}
                            lane={NEAR}
                            rest={truck.rest}
                            offset={truck.offset}
                            cab={truck.cab}
                            refused={truck.refused}
                        >
                            {(speed) =>
                                truck.kind === 'tipper' ? (
                                    <Tipper speed={speed} />
                                ) : truck.kind === 'lowbed' ? (
                                    <LowBed speed={speed} />
                                ) : (
                                    <Tanker speed={speed} />
                                )
                            }
                        </Vehicle>
                    ))}

                    {/* The traffic officer at the kerb. The scanner comes up
                        for each vehicle and flashes once as its cab passes. */}
                    <g transform={`translate(${OFFICER_X} 258) scale(1.15)`}>
                        {/* Trousers and boots. */}
                        <path d="M-7 -26H7L6 -2H1L0 -17L-1 -2H-6Z" />
                        <path
                            d="M-8 -2H-1V0H-9ZM1 -2H8L9 0H1Z"
                            fill="currentColor"
                            stroke="none"
                        />

                        {/* The arm at the side, then the jacket over it. */}
                        <path d="M8 -44L13 -35L11 -25" strokeWidth="3.5" />
                        <path d="M-9 -46Q0 -50 9 -46L8 -25H-8Z" />

                        {/* The reflective vest: its panel, the two bands
                            over the shoulders and the belt. */}
                        <path
                            d="M-6 -47L0 -37L6 -47L7 -28H-7Z"
                            fill="currentColor"
                            fillOpacity="0.28"
                            stroke="none"
                        />
                        <path d="M-6 -47L0 -37L6 -47" strokeWidth="2.5" />
                        <path d="M-8 -28H8" strokeWidth="2.5" />

                        {/* The scanning arm, jointed at the shoulder and the
                            elbow. It is drawn raised; the hv-arm rules lower
                            it, and lift it again for each vehicle. The
                            scanner and its flashes ride on the forearm. */}
                        <g className="hv-arm">
                            <path d="M-8 -44L-15 -39" strokeWidth="3.5" />
                            <g className="hv-forearm">
                                {NEAR_TRAFFIC.map((truck) => (
                                    <path
                                        key={truck.kind}
                                        className="hv-scan"
                                        style={
                                            {
                                                '--hv-dur': `${NEAR.seconds}s`,
                                                '--hv-delay': `${(checkDelay(NEAR, truck.offset, truck.cab) - 0.5).toFixed(1)}s`,
                                            } as CSSProperties
                                        }
                                        d="M-21 -64L-28 -86M-17 -65L-16 -90M-13 -64L-4 -86"
                                        strokeWidth="1.5"
                                    />
                                ))}
                                <path d="M-15 -39L-17 -52" strokeWidth="3.5" />
                                <rect
                                    x="-23"
                                    y="-63"
                                    width="12"
                                    height="10"
                                    rx="2"
                                    fill="currentColor"
                                    stroke="none"
                                />
                                <rect
                                    x="-21"
                                    y="-61"
                                    width="8"
                                    height="5"
                                    rx="1"
                                    fill="var(--brand-surface)"
                                    stroke="none"
                                />
                            </g>
                        </g>

                        {/* The head, and the white peaked cap with its badge. */}
                        <circle cx="0" cy="-55" r="5.5" />
                        <path
                            d="M-7.5 -58Q-7 -67 0 -67Q7 -67 7.5 -58Z"
                            fill="currentColor"
                        />
                        <path d="M-9 -57.5H5" strokeWidth="2.5" />
                        <circle
                            cx="0"
                            cy="-62.5"
                            r="1.6"
                            fill="var(--brand-surface)"
                            stroke="none"
                        />
                    </g>
                </g>
            </svg>
        </>
    );
}
