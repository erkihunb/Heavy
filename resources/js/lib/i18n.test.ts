import { describe, expect, it } from 'vitest';
import { translate } from '@/lib/i18n';

const translations = {
    common: {
        language: 'ቋንቋ',
        welcome: { title: 'እንኳን ደህና መጡ' },
        greeting: 'Hello :name, you have :count permits',
        plural: ':name and :names',
    },
};

describe('translate', () => {
    it('resolves a top-level key in a namespace', () => {
        expect(translate(translations, 'common.language')).toBe('ቋንቋ');
    });

    it('resolves a nested key', () => {
        expect(translate(translations, 'common.welcome.title')).toBe(
            'እንኳን ደህና መጡ',
        );
    });

    it('resolves a name that itself contains a dot', () => {
        const withDottedNames = {
            admin: {
                audit: {
                    events: {
                        'permit.transition': 'Permit status changed',
                        permit: { other: 'nested beside it' },
                    },
                },
            },
        };

        expect(
            translate(withDottedNames, 'admin.audit.events.permit.transition'),
        ).toBe('Permit status changed');
        expect(
            translate(withDottedNames, 'admin.audit.events.permit.other'),
        ).toBe('nested beside it');
        expect(
            translate(withDottedNames, 'admin.audit.events.permit.missing'),
        ).toBe('admin.audit.events.permit.missing');
    });

    it('substitutes Laravel-style placeholders', () => {
        expect(
            translate(translations, 'common.greeting', {
                name: 'Abebe',
                count: 3,
            }),
        ).toBe('Hello Abebe, you have 3 permits');
    });

    it('replaces the longest placeholder name first', () => {
        expect(
            translate(translations, 'common.plural', {
                name: 'one',
                names: 'many',
            }),
        ).toBe('one and many');
    });

    it('returns the key itself when the line is missing', () => {
        expect(translate(translations, 'common.missing')).toBe(
            'common.missing',
        );
        expect(translate(translations, 'nope.deeper.key')).toBe(
            'nope.deeper.key',
        );
    });

    it('returns the key when it points at a group rather than a line', () => {
        expect(translate(translations, 'common.welcome')).toBe(
            'common.welcome',
        );
    });
});
