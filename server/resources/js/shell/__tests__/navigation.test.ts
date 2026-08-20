import { describe, expect, it } from 'vitest';
import { isShellView, NAV_ITEMS, PRIMARY_VIEW } from '../navigation';

describe('shell navigation config', () => {
    it('declares the required primary navigation entries', () => {
        const keys = NAV_ITEMS.map((i) => i.key);
        expect(keys).toContain('today');
        expect(keys).toContain('week');
        expect(keys).toContain('calendar');
        expect(keys).toContain('goals');
        expect(keys).toContain('knowledge');
        expect(keys).toContain('canvas');
        expect(keys).toContain('analytics');
        expect(keys).toContain('settings');
    });

    it('today is the primary view', () => {
        expect(PRIMARY_VIEW).toBe('today');
    });

    it('isShellView validates known views only', () => {
        expect(isShellView('today')).toBe(true);
        expect(isShellView('settings')).toBe(true);
        expect(isShellView('nope')).toBe(false);
    });
});
