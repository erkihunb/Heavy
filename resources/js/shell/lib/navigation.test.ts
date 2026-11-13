import { describe, expect, it } from 'vitest';
import type { NavItem } from '@/config/navigation';
import { activeItem } from '@/shell/lib/navigation';

const icon = () => null as never;
const item = (href: string): NavItem => ({ href, labelKey: href, icon });

const queue = item('/queue');
const collect = item('/queue?status=awaiting_payment');
const issue = item('/queue?status=payment_confirmed');
const reports = item('/reports');
const items = [queue, collect, issue, reports];

describe('activeItem', () => {
    it('picks the item whose query the address carries', () => {
        expect(
            activeItem(items, '/queue?status=awaiting_payment'),
        ).toBe(collect);
        expect(
            activeItem(items, '/queue?status=payment_confirmed&page=2'),
        ).toBe(issue);
    });

    it('falls back to the plain item on the same path', () => {
        expect(activeItem(items, '/queue')).toBe(queue);
        expect(activeItem(items, '/queue?status=draft')).toBe(queue);
    });

    it('files a sub-page under the plain item of the nearest path', () => {
        expect(activeItem(items, '/queue/42')).toBe(queue);
        expect(activeItem(items, '/reports/export?x=1')).toBe(reports);
    });

    it('uses a query item when no plain item is visible', () => {
        expect(activeItem([collect, issue], '/queue/42')).toBe(collect);
    });

    it('matches nothing outside the menu', () => {
        expect(activeItem(items, '/vehicles')).toBeUndefined();
    });
});
