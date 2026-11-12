import { describe, expect, it } from 'vitest';
import { permitToken } from '@/lib/permit-qr';

const BASE = 'https://verify.tma.gov.et';
const HERE = 'http://127.0.0.1:8000';

describe('permitToken', () => {
    it('reads the token of a permit QR code', () => {
        expect(permitToken(`${BASE}/v/abc_DEF-123`, BASE, HERE)).toBe(
            'abc_DEF-123',
        );
        expect(permitToken(`  ${BASE}/v/abc/  `, BASE, HERE)).toBe('abc');
    });

    it('accepts codes pointing at this site', () => {
        expect(permitToken(`${HERE}/v/tok`, BASE, HERE)).toBe('tok');
    });

    it('refuses codes of other sites and other paths', () => {
        expect(
            permitToken('https://evil.example/v/tok', BASE, HERE),
        ).toBeNull();
        expect(permitToken(`${BASE}/verify`, BASE, HERE)).toBeNull();
        expect(permitToken(`${BASE}/v/tok/extra`, BASE, HERE)).toBeNull();
        expect(
            permitToken(`${BASE}/v/${'a'.repeat(65)}`, BASE, HERE),
        ).toBeNull();
        expect(permitToken('AACRA-2019-000123', BASE, HERE)).toBeNull();
        expect(permitToken('javascript:alert(1)', BASE, HERE)).toBeNull();
    });

    it('works without a configured verification address', () => {
        expect(permitToken(`${HERE}/v/tok`, '', HERE)).toBe('tok');
        expect(permitToken(`${BASE}/v/tok`, '', HERE)).toBeNull();
    });
});
