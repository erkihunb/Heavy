import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AppActionMenu, ListPagination } from '@/shell/Components/ui';
import LocalizedUiProvider from '@/shell/Components/ui/LocalizedUiProvider';

/*
 * The pagination and row action parts ported from the source system, rendered
 * with the shell's translations (lang/en/shell.php).
 */

const { get, page } = vi.hoisted(() => ({
    get: vi.fn(),
    page: {
        url: '/vehicles',
        props: {
            locale: 'en',
            locales: { en: 'English', am: 'አማርኛ' },
            translations: {
                shell: {
                    common: {
                        no_results: 'No results found',
                        page: 'Page',
                        pagination: 'Pagination',
                        pagination_range: ':from–:to of :total',
                        previous: 'Previous',
                        next: 'Next',
                        filter: 'Filter',
                        filters: 'Filters',
                        reset: 'Reset',
                    },
                    ui: {
                        actions: 'Actions',
                        columns: 'Columns',
                        density: 'Row density',
                        table_tools: 'Table tools',
                    },
                },
            },
        },
    },
}));

vi.mock('@inertiajs/react', () => ({
    router: { get, on: () => () => {} },
    usePage: () => page,
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string;
        children: React.ReactNode;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

vi.mock('@/routes/locale', () => ({ update: { url: () => '/locale' } }));

afterEach(() => {
    cleanup();
    get.mockClear();
    localStorage.clear();
});

describe('ListPagination', () => {
    const meta = {
        current_page: 5,
        last_page: 17,
        per_page: 20,
        total: 340,
        from: 81,
        to: 100,
    };

    it('shows the range and numbered pages around the current one', () => {
        render(
            <LocalizedUiProvider>
                <ListPagination meta={meta} onPageChange={() => {}} />
            </LocalizedUiProvider>,
        );

        expect(screen.getByRole('status').textContent).toBe('81–100 of 340');
        const nav = screen.getByRole('navigation', { name: 'Pagination' });
        const pages = within(nav)
            .getAllByRole('button', { name: /^Page / })
            .map((button) => button.textContent);
        expect(pages[0]).toBe('1');
        expect(pages).toContain('5');
        expect(pages[pages.length - 1]).toBe('17');
        expect(nav.textContent).toContain('…');
        expect(
            screen
                .getByRole('button', { name: 'Page 5' })
                .getAttribute('aria-current'),
        ).toBe('page');
    });

    it('moves to the page that was chosen', async () => {
        const onPageChange = vi.fn();
        render(
            <LocalizedUiProvider>
                <ListPagination meta={meta} onPageChange={onPageChange} />
            </LocalizedUiProvider>,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Next' }));
        await userEvent.click(screen.getByRole('button', { name: 'Page 17' }));

        expect(onPageChange.mock.calls).toEqual([[6], [17]]);
    });

    it('renders nothing for an empty list', () => {
        const { container } = render(
            <ListPagination
                meta={{ ...meta, total: 0 }}
                onPageChange={() => {}}
            />,
        );

        expect(container.innerHTML).toBe('');
    });
});

describe('AppActionMenu', () => {
    it('lists the visible actions and drops hidden ones', async () => {
        render(
            <LocalizedUiProvider>
                <AppActionMenu
                    items={[
                        { label: 'Edit', href: '/vehicles/1/edit' },
                        {
                            label: 'Delete',
                            variant: 'danger',
                            onClick: () => {},
                            show: false,
                        },
                    ]}
                />
            </LocalizedUiProvider>,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Actions' }));

        expect(screen.getByRole('menuitem', { name: 'Edit' })).toBeTruthy();
        expect(screen.queryByRole('menuitem', { name: 'Delete' })).toBeNull();
    });

    it('shows no trigger when no action is visible', () => {
        render(
            <LocalizedUiProvider>
                <AppActionMenu
                    items={[{ label: 'Edit', href: '/x', show: false }]}
                />
            </LocalizedUiProvider>,
        );

        expect(screen.queryByRole('button', { name: 'Actions' })).toBeNull();
    });
});
