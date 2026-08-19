import { describe, expect, it } from 'vitest';
import { taskStates } from '../derive';
import { isSchedulerReason, reasonLabel, SCHEDULER_REASONS } from '../explanation';
import { visualState, VISUAL_STATES, type VisualStateValue } from '../types';

describe('taskStates (derive)', () => {
    it('returns locked and conflict states', () => {
        expect(taskStates({ locked: true, conflict: true }, '2026-08-19')).toEqual(['locked', 'conflict']);
    });

    it('marks a past-due non-terminal task as overdue', () => {
        expect(taskStates({ status: 'scheduled', dueAt: '2026-08-10' }, '2026-08-19')).toContain('overdue');
    });

    it('does not mark a future-due task as overdue', () => {
        expect(taskStates({ status: 'scheduled', dueAt: '2026-08-25' }, '2026-08-19')).not.toContain('overdue');
    });

    it('does not mark terminal tasks as overdue', () => {
        expect(taskStates({ status: 'completed', dueAt: '2026-08-10' }, '2026-08-19')).not.toContain('overdue');
    });

    it('returns empty for a normal task', () => {
        expect(taskStates({ status: 'backlog', dueAt: null }, '2026-08-19')).toEqual([]);
    });
});

describe('visual state definitions', () => {
    it('covers all required states with a non-color glyph', () => {
        const required: VisualStateValue[] = ['locked', 'conflict', 'overdue', 'draft', 'proposed', 'offline', 'syncing', 'queued', 'failed'];
        for (const state of required) {
            expect(VISUAL_STATES[state]).toBeDefined();
            expect(VISUAL_STATES[state].glyph).toBeTruthy();
        }
    });

    it('uses a dashed pattern for conflict/overdue/offline/queued (non-color signal)', () => {
        expect(visualState('conflict').dashed).toBe(true);
        expect(visualState('overdue').dashed).toBe(true);
        expect(visualState('offline').dashed).toBe(true);
        expect(visualState('queued').dashed).toBe(true);
    });
});

describe('scheduler explanation reason codes', () => {
    it('exposes the domain reason codes with labels', () => {
        const codes = SCHEDULER_REASONS.map((r) => r.code);
        expect(codes).toContain('HARD_CONSTRAINT_FILTERED');
        expect(codes).toContain('LOCK_PROTECTED');
        expect(codes).toContain('SACRED_ANCHOR');
        expect(codes).toContain('DEADLINE_PRIORITY');
        expect(codes).toContain('CAPACITY_FIT');
        expect(codes).toContain('ENERGY_FIT');
        expect(codes).toContain('CONTEXT_SWITCH_PENALTY');
        expect(codes).toContain('PROGRESS_VALUE');
        expect(codes).toContain('CONTINUITY_PREFERENCE');
    });

    it('resolves labels and validates codes', () => {
        expect(reasonLabel('LOCK_PROTECTED')).toContain('Locked');
        expect(isSchedulerReason('DEADLINE_PRIORITY')).toBe(true);
        expect(isSchedulerReason('NOT_A_REASON')).toBe(false);
        expect(reasonLabel('NOT_A_REASON')).toBe('NOT_A_REASON');
    });
});
