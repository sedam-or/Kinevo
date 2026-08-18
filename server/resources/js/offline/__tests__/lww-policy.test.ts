import { describe, expect, it } from 'vitest';
import {
    DefaultLastWriteWinsPolicy,
    LWW_ELIGIBLE_ENTITY_TYPES,
    type ConflictContext,
} from '../lww-policy';

const policy = new DefaultLastWriteWinsPolicy();

function ctx(overrides: Partial<ConflictContext> = {}): ConflictContext {
    return {
        entityType: 'task',
        operationType: 'update',
        isVersioned: false,
        serverVersion: 5,
        localBaseVersion: 2,
        ...overrides,
    };
}

describe('DefaultLastWriteWinsPolicy', () => {
    describe('isLwwEligible', () => {
        it('allows low-risk task mutations when unversioned', () => {
            expect(policy.isLwwEligible('task', 'update', false)).toBe(true);
        });

        it('allows quick-capture operations', () => {
            expect(policy.isLwwEligible('quick_capture', 'quick_capture', false)).toBe(true);
        });

        it('rejects versioned rich content (canvas, note)', () => {
            expect(policy.isLwwEligible('canvas', 'update', true)).toBe(false);
            expect(policy.isLwwEligible('note', 'update', true)).toBe(false);
        });

        it('rejects canvas/note even when unversioned (conservative entity)', () => {
            expect(policy.isLwwEligible('canvas', 'update', false)).toBe(false);
            expect(policy.isLwwEligible('note', 'update', false)).toBe(false);
        });

        it('rejects unknown entity types when unversioned', () => {
            expect(policy.isLwwEligible('mystery', 'update', false)).toBe(false);
        });
    });

    describe('resolveConflict', () => {
        it('resolves low-risk stale mutation with last-write-wins', () => {
            expect(policy.resolveConflict(ctx())).toBe('last_write_wins');
        });

        it('resolves quick-capture with last-write-wins', () => {
            expect(
                policy.resolveConflict(
                    ctx({ entityType: 'quick_capture', operationType: 'quick_capture' }),
                ),
            ).toBe('last_write_wins');
        });

        it('surfaces a conflict for versioned canvas mutation', () => {
            expect(
                policy.resolveConflict(ctx({ entityType: 'canvas', isVersioned: true })),
            ).toBe('conflict');
        });

        it('surfaces a conflict for versioned note mutation', () => {
            expect(
                policy.resolveConflict(ctx({ entityType: 'note', isVersioned: true })),
            ).toBe('conflict');
        });

        it('surfaces a conflict for a stale task carrying a baseVersion', () => {
            expect(
                policy.resolveConflict(ctx({ entityType: 'task', isVersioned: true })),
            ).toBe('conflict');
        });

        it('surfaces a conflict for an unknown entity', () => {
            expect(
                policy.resolveConflict(ctx({ entityType: 'attachment', isVersioned: false })),
            ).toBe('conflict');
        });
    });

    it('exposes the LWW-eligible entity allow-list', () => {
        expect(LWW_ELIGIBLE_ENTITY_TYPES.has('task')).toBe(true);
        expect(LWW_ELIGIBLE_ENTITY_TYPES.has('canvas')).toBe(false);
        expect(LWW_ELIGIBLE_ENTITY_TYPES.has('note')).toBe(false);
    });

    it('is deterministic for identical inputs', () => {
        const a = policy.resolveConflict(ctx());
        const b = policy.resolveConflict(ctx());
        expect(a).toBe(b);
    });
});
