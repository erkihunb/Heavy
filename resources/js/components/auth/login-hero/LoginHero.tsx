import { useRef } from 'react';
import { useInView, usePageVisibility } from '@/hooks/use-page-visibility';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
import { RoadNetwork } from './RoadNetwork';
import { SCENE } from './scene';

/**
 * The sign-in illustration.
 *
 * Two layers, and no more. Behind: slow fields of light that drift and
 * breathe in the brand colour (the "aurora" treatment — large soft
 * gradients on long cycles). In front: the roads of the city, traced in a
 * thin line, with heavy vehicles travelling them.
 *
 * The restraint is the point. An earlier version pinned a clock face and a
 * QR code on the panel as well; they read as clip art stuck on a background
 * and crowded the text. What the system is for is already said in words
 * beside this, so the picture only has to set a mood and suggest movement.
 *
 * It runs only when it is worth running: the loops stop while the tab is in
 * the background or the panel is out of sight, and `animate` goes false for
 * anyone who asked their device for less motion, which leaves the same
 * scene standing still.
 *
 * It travels in the sign-in page's own chunk. Splitting it out was tried and
 * reverted: a page that lazy-imports part of itself is demoted by Rollup
 * from a page entry to a shared chunk, and the Blade template looks pages up
 * by their own path (docs/DECISIONS.md).
 */
export function LoginHero() {
    const reduced = useReducedMotion();
    const visible = usePageVisibility();
    const ref = useRef<HTMLDivElement>(null);
    const inView = useInView(ref);

    const animate = !reduced && visible && inView;

    return (
        <div
            ref={ref}
            aria-hidden="true"
            className="absolute inset-0 overflow-hidden"
        >
            {/* The light: three fields, each on its own long cycle, so they
                cross and part instead of pulsing together. */}
            <div
                className={`absolute -top-1/4 -left-1/4 size-[70%] rounded-full bg-white/[0.14] blur-3xl ${animate ? 'hero-aurora' : ''}`}
            />
            <div
                className={`absolute top-1/4 -right-1/4 size-[65%] rounded-full bg-white/[0.1] blur-3xl ${animate ? 'hero-aurora hero-aurora-slow' : ''}`}
            />
            <div
                className={`absolute -bottom-1/3 left-1/4 size-[60%] rounded-full bg-white/[0.07] blur-3xl ${animate ? 'hero-aurora hero-aurora-slower' : ''}`}
            />

            {/* The city and its traffic. */}
            <svg
                viewBox={`0 0 ${SCENE.width} ${SCENE.height}`}
                preserveAspectRatio="xMidYMid slice"
                className="absolute inset-0 size-full text-white"
                role="presentation"
                focusable="false"
            >
                <RoadNetwork animate={animate} />
            </svg>
        </div>
    );
}

export default LoginHero;
