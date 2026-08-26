import { describe, expect, it } from 'vitest';
import {
    isShellView,
    MOBILE_MORE_KEYS,
    MOBILE_PRIMARY_KEYS,
    NAV_GROUPS,
    NAV_ITEMS,
    PRIMARY_VIEW,
} from '../navigation';

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

    it('groups navigation into EXECUTE/PLAN/KNOWLEDGE/REVIEW/SYSTEM (design.md §9, §104)', () => {
        const groupKeys = NAV_GROUPS.map((g) => g.key);
        expect(groupKeys).toEqual(['EXECUTE', 'PLAN', 'KNOWLEDGE', 'REVIEW', 'SYSTEM']);

        const itemsFor = (group: string) => NAV_GROUPS.find((g) => g.key === group)?.items.map((i) => i.key) ?? [];
        expect(itemsFor('EXECUTE')).toEqual(['today', 'week', 'calendar']);
        expect(itemsFor('PLAN')).toEqual(['goals', 'tasks', 'schedule']);
        expect(itemsFor('KNOWLEDGE')).toEqual(['knowledge', 'canvas']);
        expect(itemsFor('REVIEW')).toEqual(['analytics']);
        expect(itemsFor('SYSTEM')).toEqual(['settings', 'ai-settings', 'workspace-home']);
    });

    it('each nav item belongs to exactly one group', () => {
        const all = NAV_GROUPS.flatMap((g) => g.items).map((i) => i.key);
        const unique = new Set(all);
        expect(unique.size).toBe(all.length);
        expect(all.length).toBe(NAV_ITEMS.length);
    });

    it('keeps schedule in PLAN, outside the SYSTEM settings group', () => {
        const planKeys = NAV_GROUPS.find((g) => g.key === 'PLAN')?.items.map((i) => i.key) ?? [];
        const systemKeys = NAV_GROUPS.find((g) => g.key === 'SYSTEM')?.items.map((i) => i.key) ?? [];
        expect(planKeys).toContain('schedule');
        expect(systemKeys).not.toContain('schedule');
    });

    it('declares mobile primary subset and remaining "More" views (design.md §8.3)', () => {
        expect(MOBILE_PRIMARY_KEYS).toEqual(['today', 'tasks', 'goals', 'knowledge']);
        const primarySet = new Set(MOBILE_PRIMARY_KEYS);
        expect(MOBILE_MORE_KEYS.length).toBeGreaterThan(0);
        expect(MOBILE_MORE_KEYS.some((k) => primarySet.has(k))).toBe(false);
        // Every declared view is either mobile-primary or more — nothing lost.
        const everyAtOnce = new Set([...MOBILE_PRIMARY_KEYS, ...MOBILE_MORE_KEYS]);
        expect(everyAtOnce.size).toBe(NAV_ITEMS.length);
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