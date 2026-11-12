import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
// The two icons come straight from the set, as in the source kit: same linear weight and stroke as NavIcons' adapt().
import { MoonIcon } from '@solar-icons/react/linear/moon';
import { SunIcon } from '@solar-icons/react/linear/sun';
import { useState } from 'react';
import type { SVGProps } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { useLocale } from '@/shell/hooks/useLocale';
import { useSystemSettings } from '@/shell/hooks/useSystemSettings';
import {
    UI_STYLES,
    getStoredUiStyle,
    setUiStylePreference,
} from '@/shell/lib/theme';
import type { ThemePreference, UiStyle } from '@/shell/lib/theme';

const MODES: ThemePreference[] = ['system', 'light', 'dark'];

const Sun = (props: SVGProps<SVGSVGElement>) => (
    <SunIcon strokeWidth={1.5} {...props} />
);
const Moon = (props: SVGProps<SVGSVGElement>) => (
    <MoonIcon strokeWidth={1.5} {...props} />
);

const choiceClass = (selected: boolean) =>
    [
        'flex min-h-9 w-full items-center justify-between gap-3 rounded-control px-3 text-start text-sm transition-colors duration-150',
        'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary',
        selected
            ? 'bg-(--app-surface-muted) font-semibold text-(--app-foreground)'
            : 'text-(--app-foreground) hover:bg-(--app-surface-muted)',
    ].join(' ');

/**
 * The person's appearance menu: light / dark / follow the device,
 * and the interface style (Swiss, Classic, Soft UI, High contrast) when the
 * organisation allows people to choose (System settings → Appearance).
 * Light and dark are kept by Heavy's own appearance store, so every page,
 * old shell or new, shows the same mode.
 */
export default function ThemeToggle({
    variant = 'default',
}: {
    variant?: 'default' | 'toolbar';
}) {
    const { t } = useLocale();
    const { getBoolean, getString } = useSystemSettings();
    const {
        appearance: preference,
        resolvedAppearance,
        updateAppearance,
    } = useAppearance();
    const allowSwitching = getBoolean(
        'appearance.allow_user_theme_switching',
        true,
    );
    const adminStyle = getString('appearance.ui_style', 'swiss');

    const [ownStyle, setOwnStyle] = useState<UiStyle | null>(getStoredUiStyle);

    if (!allowSwitching) {
        return null;
    }

    const icon =
        resolvedAppearance === 'dark' ? (
            <Sun className="h-5 w-5" aria-hidden="true" />
        ) : (
            <Moon className="h-5 w-5" aria-hidden="true" />
        );

    const chooseStyle = (style: UiStyle | null) => {
        setUiStylePreference(style, adminStyle);
        setOwnStyle(style);
    };

    const label = `${t('shell.appearance.title')}: ${t(`shell.appearance.${preference}`)}`;

    return (
        <Popover className="relative">
            <PopoverButton
                className={
                    variant === 'toolbar'
                        ? 'flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-(--app-muted-foreground) transition-colors duration-150 hover:bg-(--app-surface-muted) hover:text-(--app-foreground) focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-hidden'
                        : 'rounded-lg p-2 text-(--app-muted-foreground) transition-colors hover:bg-(--app-surface-muted) hover:text-(--app-foreground)'
                }
                aria-label={label}
                title={label}
            >
                {icon}
            </PopoverButton>
            <PopoverPanel
                anchor="bottom end"
                transition
                className="z-50 w-72 max-w-[calc(100vw-1rem)] rounded-panel border border-(--app-border) bg-(--app-surface) p-2 text-(--app-foreground) shadow-lg outline-hidden transition duration-100 ease-out [--anchor-gap:8px] [--anchor-padding:8px] data-closed:scale-[0.98] data-closed:opacity-0"
            >
                <p className="px-3 pt-1.5 pb-1 text-[11px] font-semibold tracking-wide text-(--app-muted-foreground) uppercase">
                    {t('shell.appearance.mode')}
                </p>
                <div
                    role="radiogroup"
                    aria-label={t('shell.appearance.mode')}
                    className="grid grid-cols-3 gap-1 px-1 pb-2"
                >
                    {MODES.map((mode) => (
                        <button
                            key={mode}
                            type="button"
                            role="radio"
                            aria-checked={preference === mode}
                            onClick={() => updateAppearance(mode)}
                            className={[
                                'flex h-9 items-center justify-center rounded-control border text-sm transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-hidden',
                                preference === mode
                                    ? 'border-primary bg-primary text-(--color-on-primary,#fff)'
                                    : 'border-(--app-border-strong) bg-(--app-surface) text-(--app-foreground) hover:bg-(--app-surface-muted)',
                            ].join(' ')}
                        >
                            {t(`shell.appearance.${mode}`)}
                        </button>
                    ))}
                </div>

                <p className="border-t border-(--app-border) px-3 pt-2.5 pb-1 text-[11px] font-semibold tracking-wide text-(--app-muted-foreground) uppercase">
                    {t('shell.appearance.interface_style')}
                </p>
                <div
                    role="radiogroup"
                    aria-label={t('shell.appearance.interface_style')}
                    className="space-y-0.5 px-1 pb-1"
                >
                    <button
                        type="button"
                        role="radio"
                        aria-checked={ownStyle === null}
                        onClick={() => chooseStyle(null)}
                        className={choiceClass(ownStyle === null)}
                    >
                        <span className="min-w-0 truncate">
                            {t('shell.appearance.organization_default')}
                        </span>
                        <span className="shrink-0 text-xs text-(--app-muted-foreground)">
                            {t(`shell.appearance.styles.${adminStyle}`)}
                        </span>
                    </button>
                    {UI_STYLES.map((style) => (
                        <button
                            key={style}
                            type="button"
                            role="radio"
                            aria-checked={ownStyle === style}
                            onClick={() => chooseStyle(style)}
                            className={choiceClass(ownStyle === style)}
                        >
                            <span className="min-w-0 truncate">
                                {t(`shell.appearance.styles.${style}`)}
                            </span>
                        </button>
                    ))}
                </div>
            </PopoverPanel>
        </Popover>
    );
}
