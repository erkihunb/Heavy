import { beforeEach, describe, expect, it, vi } from 'vitest';
import { visitWithFilters, withoutEmpty } from '@/lib/filters';

const router = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/react', () => ({ router }));

describe('withoutEmpty', () => {
    it('drops the filters that are not set and keeps the rest as they are', () => {
        expect(
            withoutEmpty({
                search: 'abc',
                role: '',
                status: null,
                branch: undefined,
                page: 0,
                archived: false,
            }),
        ).toEqual({ search: 'abc', page: 0, archived: false });
    });
});

describe('visitWithFilters', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('opens the list with only the filters that are set, in place of the current entry', () => {
        visitWithFilters('/users', { search: 'abc', role: '' });

        expect(router.get).toHaveBeenCalledExactlyOnceWith(
            '/users',
            { search: 'abc' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });
});
