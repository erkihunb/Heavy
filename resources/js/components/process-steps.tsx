import {
    Banknote,
    ClipboardCheck,
    FileBadge,
    FilePenLine,
    ScanQrCode,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const steps: {
    key: 'apply' | 'review' | 'pay' | 'issue' | 'verify';
    icon: LucideIcon;
}[] = [
    { key: 'apply', icon: FilePenLine },
    { key: 'review', icon: ClipboardCheck },
    { key: 'pay', icon: Banknote },
    { key: 'issue', icon: FileBadge },
    { key: 'verify', icon: ScanQrCode },
];

/**
 * The five stages a permit goes through, drawn as stops along a road.
 * `tone="asphalt"` is for dark bands, `tone="paper"` for cards.
 */
export function ProcessSteps({
    tone = 'paper',
    className,
}: {
    tone?: 'paper' | 'asphalt';
    className?: string;
}) {
    const { t } = useTranslation();
    const onAsphalt = tone === 'asphalt';

    return (
        <div className={cn('relative', className)}>
            {/* The road the stops sit on (desktop only). */}
            <div
                aria-hidden="true"
                className={cn(
                    'absolute top-[1.625rem] right-[10%] left-[10%] hidden lane-dash lg:block',
                    onAsphalt ? 'text-signal/70' : 'text-foreground/25',
                )}
            />

            <ol className="relative grid gap-8 lg:grid-cols-5 lg:gap-6">
                {steps.map(({ key, icon: Icon }, index) => (
                    <li
                        key={key}
                        className="flex gap-4 lg:flex-col lg:items-center lg:gap-5 lg:text-center"
                    >
                        <span
                            className={cn(
                                'relative flex size-14 shrink-0 items-center justify-center rounded-2xl border-2',
                                onAsphalt
                                    ? 'border-signal bg-asphalt text-signal'
                                    : 'border-foreground bg-signal text-signal-foreground',
                            )}
                        >
                            <Icon aria-hidden="true" className="size-6" />
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'absolute -top-2.5 -right-2.5 flex size-6 items-center justify-center rounded-full text-xs font-extrabold tabular-nums',
                                    onAsphalt
                                        ? 'bg-signal text-signal-foreground'
                                        : 'bg-foreground text-background',
                                )}
                            >
                                {index + 1}
                            </span>
                        </span>

                        <div className="space-y-1.5">
                            <h3 className="text-base leading-snug font-bold">
                                {t(`process.steps.${key}.title`)}
                            </h3>
                            <p
                                className={cn(
                                    'text-sm leading-relaxed',
                                    onAsphalt
                                        ? 'text-asphalt-muted'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {t(`process.steps.${key}.body`)}
                            </p>
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}
