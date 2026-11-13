import { useEffect, useRef } from 'react';

/*
 * System settings → Appearance: the interface style, button and card corners
 * and table density show on the page as soon as they are chosen, before
 * saving. Each changed choice is written on <html data-*> (where the CSS in
 * app.css reads it); a choice set back to its saved value, Discard, or
 * leaving the page puts back what was there before.
 */
const LIVE_FIELDS = {
    permits__appearance__ui_style: 'uiStyle',
    permits__appearance__button_style: 'buttonStyle',
    permits__appearance__card_radius: 'cardRadius',
    permits__appearance__table_density: 'tableDensity',
} as const;

type Values = Record<string, unknown>;

export function useLiveAppearance(
    values: Values | null,
    savedValues: Values,
): void {
    // The saved values as the page was opened: saving reloads the page.
    const saved = useRef(savedValues);
    // What <html> had before this page changed it, per data-* key.
    const before = useRef<Record<string, string | undefined>>({});

    useEffect(() => {
        if (values === null || typeof document === 'undefined') {
            return;
        }

        const root = document.documentElement;

        for (const [field, key] of Object.entries(LIVE_FIELDS)) {
            if (!(field in values)) {
                continue;
            }

            const value = values[field];
            const changed =
                typeof value === 'string' &&
                value !== '' &&
                value !== saved.current[field];

            if (changed) {
                if (!(key in before.current)) {
                    before.current[key] = root.dataset[key];
                }

                root.dataset[key] = value;
            } else if (key in before.current) {
                restore(root, key, before.current[key]);
                delete before.current[key];
            }
        }
    }, [values]);

    // Leaving the page without saving: put everything back.
    useEffect(() => {
        const snapshot = before.current;

        return () => {
            const root = document.documentElement;

            for (const [key, value] of Object.entries(snapshot)) {
                restore(root, key, value);
                delete snapshot[key];
            }
        };
    }, []);
}

function restore(root: HTMLElement, key: string, value: string | undefined) {
    if (value === undefined) {
        delete root.dataset[key];
    } else {
        root.dataset[key] = value;
    }
}
