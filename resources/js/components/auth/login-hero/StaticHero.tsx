import { RoadNetwork } from './RoadNetwork';
import { SCENE } from './scene';

/**
 * The same scene with every loop switched off: the light sitting where it
 * is, the vehicles standing on their roads.
 *
 * LoginHero draws exactly this when motion is not wanted; this component is
 * for tests, and for anywhere a plain version of the scene is needed.
 */
export function StaticHero() {
    return (
        <div aria-hidden="true" className="absolute inset-0 overflow-hidden">
            <div className="absolute -top-1/4 -left-1/4 size-[70%] rounded-full bg-white/[0.14] blur-3xl" />
            <div className="absolute top-1/4 -right-1/4 size-[65%] rounded-full bg-white/[0.1] blur-3xl" />
            <div className="absolute -bottom-1/3 left-1/4 size-[60%] rounded-full bg-white/[0.07] blur-3xl" />

            <svg
                viewBox={`0 0 ${SCENE.width} ${SCENE.height}`}
                preserveAspectRatio="xMidYMid slice"
                className="absolute inset-0 size-full text-white"
                role="presentation"
                focusable="false"
            >
                <RoadNetwork animate={false} />
            </svg>
        </div>
    );
}
