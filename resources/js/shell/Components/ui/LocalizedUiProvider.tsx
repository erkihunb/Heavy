import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { useActionIconRules } from '@/shell/Components/ui/actionIcons';
import { useLocale } from '@/shell/hooks/useLocale';
import { UiProvider } from '@app/ui';

/**
 * After a submit comes back with validation errors, move focus to the first
 * field marked invalid (or, failing that, the first inline error), so keyboard
 * and screen-reader users land on the problem instead of staying on the Save
 * button. One listener for every form: the kit marks fields `aria-invalid`
 * and errors `role="alert"`, so no page needs its own code.
 */
function useFocusFirstError(): void {
    useEffect(
        () =>
            router.on('error', () => {
                // Let React commit the errors before looking for them.
                window.setTimeout(() => {
                    const active = document.activeElement;
                    if (
                        active instanceof HTMLElement &&
                        active.getAttribute('aria-invalid') === 'true'
                    )
                        return;
                    // A field marked invalid; otherwise the control that shares a wrapper with the first inline error
                    // (FormField renders label, control and error in one block); otherwise the error text itself.
                    const marked = document.querySelector<HTMLElement>(
                        '[aria-invalid="true"]',
                    );
                    const alert = marked
                        ? null
                        : document.querySelector<HTMLElement>('[role="alert"]');
                    const sibling =
                        alert?.parentElement?.querySelector<HTMLElement>(
                            'input:not([type="hidden"]), select, textarea, [role="combobox"], [contenteditable="true"]',
                        ) ?? null;
                    const field = marked ?? sibling;
                    const target = field ?? alert;
                    if (!target) return;
                    if (!field && !target.hasAttribute('tabindex'))
                        target.setAttribute('tabindex', '-1');
                    const reduceMotion =
                        window.matchMedia('(prefers-reduced-motion: reduce)')
                            .matches ||
                        document.documentElement.dataset.animations === 'off';
                    target.scrollIntoView({
                        block: 'center',
                        behavior: reduceMotion ? 'auto' : 'smooth',
                    });
                    target.focus({ preventScroll: true });
                }, 50);
            }),
        [],
    );
}

/** The kit's messages and action icons in the current language. */
export default function LocalizedUiProvider({
    children,
}: {
    children: ReactNode;
}) {
    const { t } = useLocale();
    useFocusFirstError();
    const actionIcons = useActionIconRules(t);
    return (
        <UiProvider
            actionIcons={actionIcons}
            messages={{
                actions: t('shell.ui.actions'),
                cancel: t('shell.common.cancel'),
                clear: t('shell.common.clear'),
                confirm: t('shell.common.confirm'),
                filter: t('shell.common.filter'),
                filters: t('shell.common.filters'),
                loading: t('shell.common.loading'),
                noResults: t('shell.common.no_results'),
                next: t('shell.common.next'),
                page: t('shell.common.page'),
                pagination: t('shell.common.pagination'),
                previous: t('shell.common.previous'),
                range: t('shell.common.pagination_range'),
                remove: t('shell.common.remove'),
                replace: t('shell.common.replace'),
                required: t('shell.common.required'),
                reset: t('shell.common.reset'),
                results: t('shell.common.results'),
                search: t('shell.common.search'),
                columns: t('shell.ui.columns'),
                showAllColumns: t('shell.ui.show_all_columns'),
                density: t('shell.ui.density'),
                compact: t('shell.ui.compact'),
                comfortable: t('shell.ui.comfortable'),
                stickyHeader: t('shell.ui.sticky_header'),
                resetTable: t('shell.ui.reset_table'),
                sortedOnPage: t('shell.ui.sorted_on_page'),
                sortBy: t('shell.ui.sort_by'),
                tableTools: t('shell.ui.table_tools'),
                fileMaxSize: t('shell.ui.file_max_size'),
            }}
        >
            {children}
        </UiProvider>
    );
}
