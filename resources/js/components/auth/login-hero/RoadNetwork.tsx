import { ROADS, TRAFFIC } from './scene';
import { Truck } from './Truck';

type RoadNetworkProps = {
    /** false freezes every loop and places each vehicle at its resting point. */
    animate: boolean;
};

/**
 * The roads and the vehicles on them.
 *
 * Each road is drawn twice: a faint full-length line, and a bright dashed
 * copy whose dash offset travels, which reads as a route being traced. The
 * vehicles follow the same paths through SVG `offset-path`, so a vehicle can
 * never leave its road however the panel is sized.
 *
 * `offset-path` needs the path repeated as a CSS value. These come from the
 * ROADS constant in scene.ts, never from anything a person supplies.
 */
export function RoadNetwork({ animate }: RoadNetworkProps) {
    return (
        <g>
            {ROADS.map((road) => (
                <g key={road.id}>
                    <path
                        d={road.d}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={road.width}
                        strokeLinecap="round"
                        opacity={road.opacity * 0.4}
                    />
                    <path
                        className={animate ? 'hero-trace' : undefined}
                        d={road.d}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={road.width}
                        strokeLinecap="round"
                        strokeDasharray="26 34"
                        opacity={road.opacity}
                    />
                </g>
            ))}

            {TRAFFIC.map((entry) => {
                const road = ROADS.find((item) => item.id === entry.road);

                if (!road) {
                    return null;
                }

                return (
                    <g
                        key={`${entry.variant}-${entry.road}`}
                        className={animate ? 'hero-haul' : 'hero-haul-static'}
                        style={{
                            offsetPath: `path("${road.d}")`,
                            animationDuration: `${entry.duration}s`,
                            animationDelay: `${entry.delay}s`,
                        }}
                    >
                        <Truck variant={entry.variant} scale={entry.scale} />
                    </g>
                );
            })}
        </g>
    );
}
