export type SchedulerReasonCode =
    | 'HARD_CONSTRAINT_FILTERED'
    | 'LOCK_PROTECTED'
    | 'SACRED_ANCHOR'
    | 'DEADLINE_PRIORITY'
    | 'CAPACITY_FIT'
    | 'ENERGY_FIT'
    | 'CONTEXT_SWITCH_PENALTY'
    | 'PROGRESS_VALUE'
    | 'CONTINUITY_PREFERENCE';

export interface SchedulerReason {
    code: SchedulerReasonCode;
    label: string;
}

export const SCHEDULER_REASONS: SchedulerReason[] = [
    { code: 'HARD_CONSTRAINT_FILTERED', label: 'Alternatives were rejected by hard constraints' },
    { code: 'LOCK_PROTECTED', label: 'Locked task protected from automation' },
    { code: 'SACRED_ANCHOR', label: 'Sacred Anchor study commitment placed and locked' },
    { code: 'DEADLINE_PRIORITY', label: 'Nearest deadline prioritized' },
    { code: 'CAPACITY_FIT', label: 'Task fits available slot capacity' },
    { code: 'ENERGY_FIT', label: 'High energy/cognitive fit signal' },
    { code: 'CONTEXT_SWITCH_PENALTY', label: 'Context switch penalty considered' },
    { code: 'PROGRESS_VALUE', label: 'High progress leverage prioritized' },
    { code: 'CONTINUITY_PREFERENCE', label: 'Continuation of in-flight work preferred' },
];

const REASON_BY_CODE: Record<SchedulerReasonCode, string> = Object.fromEntries(
    SCHEDULER_REASONS.map((r) => [r.code, r.label]),
) as Record<SchedulerReasonCode, string>;

export function reasonLabel(code: string): string {
    return REASON_BY_CODE[code as SchedulerReasonCode] ?? code;
}

export function isSchedulerReason(code: string): code is SchedulerReasonCode {
    return code in REASON_BY_CODE;
}
