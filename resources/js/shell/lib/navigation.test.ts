import { describe, expect, it } from 'vitest';
import type { NavItem } from '@/config/navigation';
import { activeItem } from '@/shell/lib/navigation';

const icon = () => null as never;
const item = (href: string): NavItem => ({ href, labelKey: href, icon });

const queue = item('/staff/permits');
const collect = item('/staff/permits?status=awaiting_payment');
const issue = item('/staff/permits?status=payment_confirmed');
const reports = item('/staff/reports');
const items = [queue, collect, issue, reports];

describe('activeItem', () => {
    it('picks the item whose query the address carries', () => {
        expect(
            activeItem(items, '/staff/permits?status=awaiting_payment'),
        ).toBe(collect);
        expect(
            activeItem(items, '/staff/permits?status=payment_confirmed&page=2'),
        ).toBe(issue);
    });

    it('falls back to the plain item on the same path', () => {
        expect(activeItem(items, '/staff/permits')).toBe(queue);
        expect(activeItem(items, '/staff/permits?status=draft')).toBe(queue);
    });

    it('files a sub-page under the plain item of the nearest path', () => {
        expect(activeItem(items, '/staff/permits/42')).toBe(queue);
        expect(activeItem(items, '/staff/reports/export?x=1')).toBe(reports);
    });

    it('uses a query item when no plain item is visible', () => {
        expect(activeItem([collect, issue], '/staff/permits/42')).toBe(collect);
    });

    it('matches nothing outside the menu', () => {
        expect(activeItem(items, '/vehicles')).toBeUndefined();
    });
});
