import { describe, expect, it } from 'vitest';
import { isShellView, NAV_GROUPS, NAV_ITEMS, PRIMARY_VIEW } from '../navigation';

describe('shell navigation config', () => {
    it('declares the required primary navigation entries', () => {
        const keys = NAV_ITEMS.map((i) => i.key);
        expect(keys).toContain('today');
        expect(keys).toContain('week');
        expect(keys).toContain('calendar');
        expect(keys).toContain('goals');
        expect(keys).toContain('tasks');
        expect(keys).toContain('knowledge');
        expect(keys).toContain('canvas');
        expect(keys).toContain('analytics');
        expect(keys).toContain('schedule');
        expect(keys).toContain('settings');
    });

    it('groups navigation into EXECUTE/PLAN/KNOWLEDGE/REVIEW/SYSTEM (design.md §9)', () => {
        const groupKeys = NAV_GROUPS.map((g) => g.key);
        expect(groupKeys).toEqual(['EXECUTE', 'PLAN', 'KNOWLEDGE', 'REVIEW', 'SYSTEM']);

        const itemsFor = (group: string) => NAV_GROUPS.find((g) => g.key === group)?.items.map((i) => i.key) ?? [];
        expect(itemsFor('EXECUTE')).toEqual(['today', 'week', 'calendar']);
        expect(itemsFor('PLAN')).toEqual(['goals', 'tasks']);
        expect(itemsFor('KNOWLEDGE')).toEqual(['knowledge', 'canvas']);
        expect(itemsFor('REVIEW')).toEqual(['analytics']);
        expect(itemsFor('SYSTEM')).toEqual(['schedule', 'settings']);
    });

    it('today is the primary view', () => {
        expect(PRIMARY_VIEW).toBe('today');
    });

    it('isShellView validates known views only', () => {
        expect(isShellView('today')).toBe(true);
        expect(isShellView('settings')).toBe(true);
        expect(isShellView('schedule')).toBe(true);
        expect(isShellView('nope')).toBe(false);
    });
});
