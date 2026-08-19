import type { VisualStateValue } from './types';

export interface StateSource {
    locked?: boolean;
    conflict?: boolean;
    status?: string;
    dueAt?: string | null;
}

export const TERMINAL_STATUSES = ['completed', 'skipped'];

/**
 * Derive the consistent visual states for a task/event (TASK-109).
 * Color is never the only signal — each state carries a glyph + optional
 * dashed pattern and is rendered via VisualStateBadge.
 */
export function taskStates(source: StateSource, today: string): VisualStateValue[] {
    const states: VisualStateValue[] = [];

    if (source.locked) {
        states.push('locked');
    }
    if (source.conflict) {
        states.push('conflict');
    }

    const terminal = source.status !== undefined && TERMINAL_STATUSES.includes(source.status);
    if (!terminal && source.dueAt && source.dueAt.slice(0, 10) < today) {
        states.push('overdue');
    }

    return states;
}
