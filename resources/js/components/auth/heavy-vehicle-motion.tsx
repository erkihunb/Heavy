import { createContext, useContext, useEffect, useRef } from 'react';
import type { CSSProperties, ReactNode } from 'react';

/**
 * The moving picture on the sign-in page: heavy vehicles crossing Addis
 * Ababa. It fills the brand panel's free space and leaves the centre, where
 * the Authority's mark and names are, alone.
 *
 * - Along the bottom: the Entoto ridge, the city's skyline with a dome and
 *   a building still going up — bare frame and scaffolding above its
 *   finished floors, a tower crane lowering a beam to it — the light-rail viaduct with a train on it, and a
 *   two-lane road. Five different heavy vehicles use the road — a low-bed
 *   with a chained load, a tipper and a fuel tanker in the near lane, a
 *   concrete mixer and a container truck coming the other way in the far
 *   lane.
 * - Along the top: a few clouds drifting across the sky, an aeroplane
 *   crossing with its trail behind it — lower than the midday sun, and in
 *   front of it, as an aeroplane is.
 * - Between the two, under the Authority's mark and names and over the
 *   rooftops: by day, a scatter of birds. Each flies its own line — its own
 *   height, speed and heading, climbing or sinking a little on the way —
 *   and flies the way a bird does: a few wing-beats that lift it, then a
 *   glide on set wings that lets it sink, then again.
 *
 * Three things in it are the system itself rather than scenery:
 *
 * - The restricted hours. The scene has a day and a night: the sun crosses
 *   and sets, the panel deepens to a dark green, windows and headlights come
 *   on, the moon rises over a sky of stars — many faint, a few bright, each
 *   twinkling at its own pace, and now and then one falling — and the heavy vehicles keep moving through all of it.
 * - The permit. Each vehicle in the near lane is one that holds a permit.
 * - The roadside check. A traffic officer stands at the kerb, scanner at
 *   their side; as each vehicle comes up they raise it, and as the cab
 *   passes the scanner flashes, the permit's QR code shows over
 *   that vehicle with the scan line crossing it, and the code then gives way
 *   to the answer, which travels on with the vehicle: a tick for a permit in
 *   force, or a red cross for one that is refused (expired, revoked, not
 *   found). One of the three vehicles is refused.
 * - What a refusal means. A boom gate stands a little way past the officer.
 *   It lifts for a vehicle whose permit was accepted and comes down behind
 *   it. The refused vehicle never reaches it: it slows, pulls into the
 *   lay-by beside the officer and stops there with its hazard lights going,
 *   and is gone before the next vehicle arrives.
 *
 * The scene also has a little depth: as the pointer crosses the panel, the
 * ridge, the skyline, the viaduct and the road slide by different amounts.
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

/* Where the refused vehicle stops: this far along, this far into the lay-by.
   The hv-refused keyframes in app.css use the same two numbers. */
const STOP = { x: 167, layBy: 14 };

/* The order and spacing here are what keep the lane clear: the refused
   vehicle has the longest gap behind it, long enough to stop in the lay-by
   and be gone before the next one's nose reaches where it stood, and the two
   accepted ones follow each other with a gap between tail and nose. Nothing
   in this lane ever overlaps anything else in it. */
/* The near lane: what drives there, where it rests, how far round the loop
   it starts, where along its length the cab is, and whether the officer's
   scan refuses it. */
const NEAR_TRAFFIC = [
    { kind: 'tipper', rest: 620, offset: 0.92, cab: 146, refused: false },
    { kind: 'lowbed', rest: STOP.x, offset: 0.34, cab: 238, refused: true },
    { kind: 'tanker', rest: -130, offset: 0.67, cab: 238, refused: false },
] as const;

/* The colour of a refused scan; the tick keeps the panel's own white. */
const REFUSED = '#ef4444';

/* It enters two-thirds of the way through the near lane's loop, which is
   what brings its cab to the officer when the arm is up for it. */
const REFUSED_DELAY = `${(-(1 - 0.66) * NEAR.seconds).toFixed(2)}s`;

/* True inside the vehicle that stops, so its wheels stop with it. */
const Stopping = createContext(false);

/* How far a layer slides, in units, when the pointer is at the panel's edge. */
const depth = (units: number) => ({ '--hv-depth': units }) as CSSProperties;

/**
 * A tyre, a hub and spokes; it turns once per circumference travelled. On
 * the vehicle that stops, it turns exactly as far as that vehicle rolls
 * before stopping, and then holds.
 */
function Wheel({ x, r = 13, speed }: { x: number; r?: number; speed: number }) {
    const stopping = useContext(Stopping);
    const spoke = r - 3;
    const spin = ((2 * Math.PI * r) / speed).toFixed(2);
    const turn = (((STOP.x + 260) / (2 * Math.PI * r)) * 360).toFixed(0);

    return (
        <g
            className={stopping ? 'hv-wheel hv-wheel-stopping' : 'hv-wheel'}
            style={
                stopping
                    ? ({ '--hv-turn': `${turn}deg` } as CSSProperties)
                    : ({ '--hv-spin': `${spin}s` } as CSSProperties)
            }
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
                className={
                    refused ? 'hv-refused' : leftward ? 'hv-left' : 'hv-right'
                }
                style={
                    {
                        '--hv-dur': `${lane.seconds}s`,
                        '--hv-delay': refused
                            ? REFUSED_DELAY
                            : `${(-offset * lane.seconds).toFixed(1)}s`,
                        transform: refused
                            ? `translate(${STOP.x}px, ${STOP.layBy}px)`
                            : `translateX(${rest}px)`,
                    } as CSSProperties
                }
            >
                <g
                    transform={`scale(${leftward ? -lane.scale : lane.scale} ${lane.scale})`}
                >
                    <Stopping.Provider value={refused}>
                        {children(speed)}
                    </Stopping.Provider>
                    {refused && cab !== undefined && (
                        <g className="hv-hazards" fill="#f59e0b" stroke="none">
                            <circle
                                className="hv-hazard"
                                cx="3"
                                cy="-38"
                                r="3"
                            />
                            <circle
                                className="hv-hazard"
                                cx={cab + 23}
                                cy="-44"
                                r="3"
                            />
                        </g>
                    )}
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
    [442, 48, 60],
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

/* The birds, each on its own: height in their band, size, seconds to cross,
   how far across it starts, whether it flies leftward, where it rests, how
   far it climbs (negative) or sinks by mid-crossing, and the seconds one
   beat-and-glide takes. They share nothing, so they never move as a group. */
const BIRDS: [
    number,
    number,
    number,
    number,
    boolean,
    number,
    number,
    number,
][] = [
    [30, 0.9, 22, 0.1, false, 150, -16, 3.4],
    [58, 0.7, 27, 0.9, true, 360, 12, 4.1],
    [84, 1.0, 19, 0.55, false, 600, -10, 3.0],
    [112, 0.8, 25, 0.3, true, 240, -18, 3.8],
    [140, 0.9, 21, 0.75, false, 470, 14, 3.3],
    [168, 0.7, 29, 0.45, true, 90, -8, 4.4],
];

/* The night sky: a few dozen stars at places that look scattered but are
   the same on every visit (worked out from the star's number, not drawn at
   random). Most are faint points; every eighth is a bright one, drawn as a
   four-pointed glint. Each has its own size, its own pace of twinkling and
   its own place in that pace. */
const STARS = Array.from({ length: 48 }, (_, index) => {
    const part = (seed: number) => {
        const value = Math.sin((index + 1) * seed) * 10000;

        return value - Math.floor(value);
    };

    return {
        x: Math.round(part(12.9898) * 800),
        y: Math.round(6 + part(78.233) * 204),
        size: Number((0.5 + part(37.719) * 1.1).toFixed(2)),
        pace: Number((2.2 + part(4.581) * 3.4).toFixed(1)),
        lag: Number((part(93.989) * 5).toFixed(1)),
        bright: index % 8 === 0,
    };
});

const GLINT = 'M0 -6Q0.7 -0.7 6 0Q0.7 0.7 0 6Q-0.7 0.7 -6 0Q-0.7 -0.7 0 -6Z';

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
    const anchor = useRef<HTMLDivElement>(null);

    /* Depth: tell the panel where the pointer is across it, from -1 at the
       start edge to 1 at the end edge, and let CSS slide the layers. Left
       alone for anyone who asked for less motion. */
    useEffect(() => {
        const panel = anchor.current?.parentElement;
        const allowed = window.matchMedia?.(
            '(prefers-reduced-motion: no-preference)',
        ).matches;

        if (!panel || !allowed) {
            return;
        }

        let frame = 0;

        const move = (event: PointerEvent) => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                const box = panel.getBoundingClientRect();
                const across = ((event.clientX - box.left) / box.width) * 2 - 1;

                panel.style.setProperty('--hv-px', across.toFixed(3));
            });
        };
        const leave = () => panel.style.setProperty('--hv-px', '0');

        panel.addEventListener('pointermove', move);
        panel.addEventListener('pointerleave', leave);

        return () => {
            cancelAnimationFrame(frame);
            panel.removeEventListener('pointermove', move);
            panel.removeEventListener('pointerleave', leave);
            panel.style.removeProperty('--hv-px');
        };
    }, []);

    return (
        <>
            {/* Night falling over the whole panel. */}
            <div
                ref={anchor}
                aria-hidden="true"
                className="hv-dusk pointer-events-none absolute inset-0 -z-10 hidden bg-[#02170b] lg:block"
            />

            {/* The sky. */}
            <svg
                {...svgProps}
                viewBox="0 0 800 220"
                preserveAspectRatio="xMidYMin slice"
                className="hv-layer pointer-events-none absolute inset-x-0 top-0 -z-10 hidden h-[28%] w-full lg:block"
                style={depth(5)}
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
                    {STARS.map((star) => (
                        <g
                            key={`${star.x}-${star.y}`}
                            transform={`translate(${star.x} ${star.y})`}
                        >
                            {star.bright ? (
                                <path
                                    className="hv-twinkle"
                                    style={
                                        {
                                            '--hv-dur': `${star.pace}s`,
                                            '--hv-delay': `-${star.lag}s`,
                                        } as CSSProperties
                                    }
                                    d={GLINT}
                                    transform={`scale(${(0.5 + star.size * 0.45).toFixed(2)})`}
                                />
                            ) : (
                                <circle
                                    className="hv-twinkle"
                                    style={
                                        {
                                            '--hv-dur': `${star.pace}s`,
                                            '--hv-delay': `-${star.lag}s`,
                                        } as CSSProperties
                                    }
                                    r={star.size}
                                />
                            )}
                        </g>
                    ))}

                    {/* One falling star a night. */}
                    <path
                        className="hv-shoot"
                        d="M0 0L44 -17"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.5"
                    />
                </g>

                {/* An aeroplane crossing, nose to the left, its trail
                    thinning out behind it and a light on its tail; after
                    dark its beacon and strobe show as well. It flies
                    below the height the sun reaches at midday and is drawn
                    after the sun, so it passes in front of it. */}
                <g transform="translate(0 136)">
                    <g
                        className="hv-left"
                        style={
                            {
                                '--hv-dur': '52s',
                                '--hv-delay': '-21s',
                                transform: 'translateX(560px)',
                            } as CSSProperties
                        }
                    >
                        <path
                            d="M82 -1H128M134 -1H160M166 -1H182"
                            strokeWidth="1.5"
                            opacity="0.35"
                        />
                        <path
                            d="M0 0Q6 -5 18 -5H58L70 -17H75L71 -5Q77 -4 77 -1Q70 3 58 3H14Q4 3 0 0Z"
                            fill="var(--brand-surface)"
                            strokeWidth="1.5"
                        />
                        <path
                            d="M30 1L49 14H55L44 1"
                            fill="var(--brand-surface)"
                            strokeWidth="1.5"
                        />
                        <path
                            d="M13 -1H52"
                            strokeWidth="1.5"
                            strokeDasharray="2 3"
                            opacity="0.6"
                        />
                        <circle
                            className="hv-beacon"
                            cx="73"
                            cy="-17"
                            r="1.8"
                            fill={REFUSED}
                            stroke="none"
                        />

                        {/* After dark: a red beacon under the belly that
                            flashes steadily, and a red strobe on the wing
                            that fires twice and waits. Each has a soft halo
                            that flashes with it. */}
                        <g className="hv-night" stroke="none">
                            <g className="hv-hazard" fill={REFUSED}>
                                <circle cx="40" cy="4.5" r="6" opacity="0.3" />
                                <circle cx="40" cy="4.5" r="2.4" />
                            </g>
                            <g className="hv-strobe" fill={REFUSED}>
                                <circle cx="52" cy="14" r="6" opacity="0.3" />
                                <circle cx="52" cy="14" r="2.2" />
                            </g>
                        </g>
                    </g>
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

            {/* The birds, in the space under the mark and the names and over
                the rooftops; out by day, roosting by night. Each one is
                three things at once: its line across (the outer group), the
                lift and sink of beating and gliding (hv-bob), and the wings
                themselves (hv-flap), on a body that stays level. */}
            <svg
                {...svgProps}
                viewBox="0 0 800 200"
                preserveAspectRatio="xMidYMid slice"
                className="hv-layer pointer-events-none absolute inset-x-0 top-[56%] -z-10 hidden h-[18%] w-full lg:block"
                style={depth(12)}
            >
                <g className="hv-day">
                    {BIRDS.map(
                        ([
                            y,
                            size,
                            seconds,
                            offset,
                            leftward,
                            rest,
                            rise,
                            beat,
                        ]) => (
                            <g key={y} transform={`translate(0 ${y})`}>
                                <g
                                    className={
                                        leftward
                                            ? 'hv-bird-left'
                                            : 'hv-bird-right'
                                    }
                                    style={
                                        {
                                            '--hv-dur': `${seconds}s`,
                                            '--hv-delay': `${(-offset * seconds).toFixed(1)}s`,
                                            '--hv-rise': `${rise}px`,
                                            '--hv-beat': `${beat}s`,
                                            '--hv-beat-delay': `${(-offset * beat).toFixed(2)}s`,
                                            transform: `translateX(${rest}px)`,
                                        } as CSSProperties
                                    }
                                >
                                    <g className="hv-bob">
                                        <g
                                            transform={`scale(${leftward ? -size : size} ${size})`}
                                        >
                                            {/* Seen from the side, as a
                                                solid shape: the far wing,
                                                the body from forked tail to
                                                beak, then the near wing.
                                                Both wings hinge at the
                                                shoulder. */}
                                            <g
                                                fill="currentColor"
                                                stroke="none"
                                            >
                                                <path
                                                    className="hv-flap"
                                                    d="M0 -1Q2 -8 -3 -13Q3 -12 5 -1Z"
                                                    opacity="0.55"
                                                />
                                                <path d="M-9 0L-14 -3L-12.5 0.3L-14 3.2L-8 1.6Q-2 4 4 2.6Q7 2.1 8.6 0.7L12 0L8.6 -0.9Q7 -2.9 4 -2.6Q-2 -3.2 -9 0Z" />
                                                <path
                                                    className="hv-flap"
                                                    d="M-3 -1Q-2 -10 -9 -16Q1 -14 4 -1Z"
                                                    opacity="0.9"
                                                />
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </g>
                        ),
                    )}
                </g>
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
                <g className="hv-layer" style={depth(4)}>
                    <path
                        d="M-20 100Q60 44 150 74T330 60T520 80T700 52T830 84"
                        opacity="0.3"
                    />
                    <path
                        d="M-20 120Q110 84 230 104T470 96T830 112"
                        strokeWidth="1.5"
                        opacity="0.2"
                    />
                </g>

                {/* The skyline: towers, a crane on a site, and a dome. */}
                <g fill="var(--brand-surface)">
                    <g className="hv-layer" style={depth(9)}>
                        <g opacity="0.55">
                            <path d="M420 176V22M396 28H474M408 28V40H400M420 22L396 28M420 22L474 28" />
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
                        {/* The building going up under the crane. Its lower
                            floors are finished (they are in BUILDINGS); above
                            them is the bare frame — columns, floor slabs and
                            the bars standing out of the top — with
                            scaffolding up one side. The crane lowers a beam
                            to the top floor and lifts away again, and wears
                            a red light after dark. */}
                        <g strokeWidth="1.5">
                            <path
                                d="M442 116V70M458 116V74M474 116V70M490 116V74M442 100H490M442 84H490"
                                strokeOpacity="0.7"
                            />
                            <path
                                d="M446 84V78M452 84V79M464 84V78M470 84V79M482 84V78"
                                strokeOpacity="0.5"
                            />
                            <path
                                d="M435 176V86M435 161H442M435 146H442M435 131H442M435 116H442M435 101H442M435 176L442 161L435 146L442 131L435 116L442 101L435 86"
                                strokeOpacity="0.45"
                            />
                            <path className="hv-cable" d="M462 28V72" />
                            <g className="hv-hoist">
                                <path d="M462 72L455 78M462 72L469 78" />
                                <rect
                                    x="452"
                                    y="78"
                                    width="20"
                                    height="4"
                                    fill="currentColor"
                                    stroke="none"
                                />
                            </g>
                            <g className="hv-night" stroke="none">
                                <circle
                                    className="hv-hazard"
                                    cx="420"
                                    cy="21"
                                    r="3"
                                    fill={REFUSED}
                                />
                            </g>
                        </g>
                        <path
                            d="M494 176A40 32 0 0 1 574 176ZM534 144V134"
                            strokeOpacity="0.55"
                            strokeWidth="1.5"
                        />
                    </g>

                    {/* The light-rail viaduct, and the train that uses it. */}
                    <g className="hv-layer" style={depth(14)}>
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
                                <rect
                                    x="0"
                                    y="-16"
                                    width="54"
                                    height="16"
                                    rx="4"
                                />
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
                    </g>

                    {/* The road: kerb, lane markings and the near edge. */}
                    <g className="hv-layer" style={depth(20)}>
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
                        <g
                            transform={`translate(${OFFICER_X} 258) scale(1.15)`}
                        >
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
                                    <path
                                        d="M-15 -39L-17 -52"
                                        strokeWidth="3.5"
                                    />
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
                        {/* The boom gate past the officer: down across the
                        near lane, up for a vehicle that was accepted. */}
                        <path d="M600 252V198" strokeWidth="4" />
                        <g className="hv-gate">
                            <rect
                                x="536"
                                y="202"
                                width="70"
                                height="7"
                                rx="3"
                                fill="currentColor"
                                stroke="none"
                            />
                            <path
                                d="M546 202V209M558 202V209M570 202V209M582 202V209"
                                stroke={REFUSED}
                                strokeWidth="5"
                                strokeLinecap="butt"
                            />
                        </g>
                        <circle cx="600" cy="205.5" r="4.5" />
                    </g>
                </g>
            </svg>
        </>
    );
}
