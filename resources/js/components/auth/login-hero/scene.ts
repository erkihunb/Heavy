/**
 * The shared geometry of the sign-in illustration.
 *
 * Every component in this folder draws inside one 600 × 760 viewBox, so the
 * roads, the vehicles on them and the two motifs line up whatever size the
 * panel is. The numbers live here rather than in each file, so a road and
 * the vehicle following it can never drift apart.
 */
export const SCENE = { width: 600, height: 760 } as const;

/** The road paths, in drawing order (back to front). */
export const ROADS = [
    {
        id: 'road-far',
        d: 'M -40 214 C 150 150, 300 262, 436 196 S 606 120, 652 156',
        width: 2,
        opacity: 0.28,
    },
    {
        id: 'road-mid',
        d: 'M -40 404 C 130 330, 268 470, 410 398 S 596 318, 652 360',
        width: 3,
        opacity: 0.42,
    },
    {
        id: 'road-near',
        d: 'M -40 606 C 160 536, 292 672, 442 592 S 604 520, 652 560',
        width: 4,
        opacity: 0.55,
    },
] as const;

export type RoadId = (typeof ROADS)[number]['id'];

export type TruckVariant = 'cargo' | 'mixer' | 'trailer';

/**
 * Which vehicle travels which road, how long it takes and when it starts.
 * The durations are deliberately unequal and the delays negative, so the
 * vehicles are already spread along the roads on the first frame instead of
 * setting off together.
 */
export const TRAFFIC: ReadonlyArray<{
    variant: TruckVariant;
    road: RoadId;
    duration: number;
    delay: number;
    scale: number;
}> = [
    { variant: 'trailer', road: 'road-near', duration: 20, delay: 0, scale: 1 },
    {
        variant: 'cargo',
        road: 'road-mid',
        duration: 16,
        delay: -6,
        scale: 0.82,
    },
    {
        variant: 'mixer',
        road: 'road-far',
        duration: 13,
        delay: -3,
        scale: 0.64,
    },
];
