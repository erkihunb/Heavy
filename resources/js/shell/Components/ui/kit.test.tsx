import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    AppActionMenu,
    AppDataTable,
    AppFilterBar,
    FilterField,
    Input,
    ListPagination,
    createColumnHelper,
} from '@/shell/Components/ui';
import LocalizedUiProvider from '@/shell/Components/ui/LocalizedUiProvider';

/*
 * The table, filter and pagination parts ported from the source system, rendered with
 * the shell's translations (lang/en/shell.php).
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

type Vehicle = { id: number; plate: string; type: string; axles?: number };
const column = createColumnHelper<Vehicle>();
const columns = [
    column.accessor('plate', { header: 'Plate' }),
    column.accessor('type', { header: 'Type' }),
    column.accessor('axles', { header: 'Axles' }),
];

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

describe('AppDataTable', () => {
    const rows = [
        { id: 1, plate: 'AA 3-12345', type: 'Truck' },
        { id: 2, plate: 'AA 3-67890', type: 'Trailer' },
    ];
    const cellsOf = (container: HTMLElement, index: number) =>
        [...container.querySelectorAll('tbody tr')].map(
            (tr) => tr.querySelectorAll('td')[index]?.textContent,
        );

    it('draws the rows under their headers, with the table tools', () => {
        const { container } = render(
            <LocalizedUiProvider>
                <AppDataTable id="vehicles" data={rows} columns={columns} />
            </LocalizedUiProvider>,
        );

        expect(
            [...container.querySelectorAll('thead th')].map(
                (th) => th.textContent,
            ),
        ).toEqual(['Plate', 'Type', 'Axles']);
        expect(cellsOf(container, 0)).toEqual(['AA 3-12345', 'AA 3-67890']);
        expect(screen.getByRole('button', { name: /Columns/ })).toBeTruthy();
    });

    it('sorts the rows on this page when a header is clicked', async () => {
        const { container } = render(
            <LocalizedUiProvider>
                <AppDataTable
                    id="vehicles-sort"
                    data={rows}
                    columns={columns}
                />
            </LocalizedUiProvider>,
        );
        const typeHeader = [
            ...container.querySelectorAll('thead th'),
        ][1] as HTMLElement;

        await userEvent.click(typeHeader);
        const first = cellsOf(container, 1);
        await userEvent.click(typeHeader);
        const second = cellsOf(container, 1);

        expect([first, second]).toEqual(
            expect.arrayContaining([
                ['Trailer', 'Truck'],
                ['Truck', 'Trailer'],
            ]),
        );
        expect(first).not.toEqual(second);
    });

    it('shows the empty state when there are no rows', () => {
        render(
            <LocalizedUiProvider>
                <AppDataTable data={[]} columns={columns} />
            </LocalizedUiProvider>,
        );

        expect(screen.getByText('No results found')).toBeTruthy();
        expect(screen.queryByRole('table')).toBeNull();
    });

    it('requests the list address, with its filters, for another page', async () => {
        render(
            <LocalizedUiProvider>
                <AppDataTable
                    data={[{ id: 1, plate: 'AA 3-12345', type: 'Truck' }]}
                    columns={columns}
                    href="/vehicles"
                    filters={{ search: 'AA', status: '' }}
                    meta={{
                        current_page: 1,
                        last_page: 3,
                        per_page: 1,
                        total: 3,
                        from: 1,
                        to: 1,
                    }}
                />
            </LocalizedUiProvider>,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Page 2' }));

        expect(get).toHaveBeenCalledWith(
            '/vehicles',
            { search: 'AA', page: 2 },
            { preserveState: true, preserveScroll: true },
        );
    });
});

describe('AppFilterBar', () => {
    it('sends the filled filters as the query string and leaves out empty ones', async () => {
        render(
            <LocalizedUiProvider>
                <AppFilterBar href="/vehicles" filters={{}}>
                    <FilterField label="Search">
                        <Input name="search" defaultValue="" />
                    </FilterField>
                    <FilterField label="Type">
                        <Input name="type" defaultValue="" />
                    </FilterField>
                </AppFilterBar>
            </LocalizedUiProvider>,
        );

        await userEvent.type(screen.getByLabelText('Search'), 'AA 3');
        await userEvent.click(screen.getByRole('button', { name: 'Filter' }));

        expect(get).toHaveBeenCalledWith(
            '/vehicles',
            { search: 'AA 3' },
            { preserveState: true },
        );
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
