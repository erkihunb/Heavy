import { describe, expect, it } from 'vitest';
import { appNameOf, appTitle } from '@/lib/app-title';

describe('appTitle', () => {
    it('puts the system name after the page title', () => {
        expect(appTitle('ዳሽቦርድ', 'AACRA')).toBe('ዳሽቦርድ - AACRA');
    });

    it('is the name alone without a page title, and the title alone without a name', () => {
        expect(appTitle('', 'AACRA')).toBe('AACRA');
        expect(appTitle('ዳሽቦርድ', '')).toBe('ዳሽቦርድ');
    });
});

describe('appNameOf', () => {
    it('reads the name the server sent with the page', () => {
        expect(appNameOf({ name: 'AACRA' })).toBe('AACRA');
    });

    it('has none when the page carries no usable name', () => {
        expect(appNameOf({})).toBeNull();
        expect(appNameOf({ name: '  ' })).toBeNull();
        expect(appNameOf(null)).toBeNull();
    });
});
