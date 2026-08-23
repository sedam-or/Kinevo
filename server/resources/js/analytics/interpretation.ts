/**
 * Deterministic "What changed / Why it matters / What to do" interpretation
 * for each analytics chart (TASK-P17-017, design.md §38/§104 P17-G). Pure
 * functions over plain data so the view stays thin and the copy is testable.
 * No new analytics logic is computed here — only read-side explanation.
 */

export interface Interpretation {
    whatChanged: string;
    whyItMatters: string;
    whatToDo: string;
}

export interface WorkLifeInterpretationInput {
    workRatio: number;
    previous: { work_ratio: number } | null;
    band: 'no_data' | 'work_leaning' | 'balanced' | 'recharge_leaning';
}

export function interpretWorkLife(input: WorkLifeInterpretationInput): Interpretation {
    const current = Math.round(input.workRatio * 100);
    const previous = input.previous ? Math.round(input.previous.work_ratio * 100) : null;

    const whatChanged = previous === null
        ? `Work share ${current}% — no earlier period to compare.`
        : `Work share ${current}% vs ${previous}% the previous period.`;

    const whatToDo = (() => {
        switch (input.band) {
            case 'work_leaning':
                return 'Protect a recharge block to rebalance.';
            case 'recharge_leaning':
                return 'Protect a focus block to lift output.';
            case 'balanced':
                return 'Keep the current rhythm.';
            default:
                return 'Track a few days of focus and recharge first.';
        }
    })();

    return { whatChanged, whyItMatters: bandMeaning(input.band), whatToDo };
}

function bandMeaning(band: WorkLifeInterpretationInput['band']): string {
    switch (band) {
        case 'work_leaning':
            return 'Work outweighs recharge — sustained output without recovery risks fatigue.';
        case 'recharge_leaning':
            return 'Recharge outweighs work — recovery is healthy but output is low.';
        case 'balanced':
            return 'Work and recharge are balanced — a sustainable split.';
        default:
            return 'Not enough tracked time to read a pattern yet.';
    }
}

export interface GoalInterpretationInput {
    completionRate: number;
    totalMilestones: number;
    completedMilestones: number;
    overdue: number;
    atRisk: number;
}

export function interpretGoals(input: GoalInterpretationInput): Interpretation {
    const rate = Math.round(input.completionRate * 100);
    const whatChanged = `${input.completedMilestones}/${input.totalMilestones} milestones · ${rate}% of goal tasks complete.`;

    const pressure = input.overdue + input.atRisk;
    const whyItMatters = pressure > 0
        ? `${pressure} goal${pressure > 1 ? 's are' : ' is'} at risk of missing its deadline.`
        : 'No goal is pressing on a deadline right now.';

    const whatToDo = pressure > 0
        ? 'Review the at-risk goal and its milestone plan.'
        : 'Keep completing scheduled goal tasks.';

    return { whatChanged, whyItMatters, whatToDo };
}

export interface CapacityInterpretationInput {
    realization: number;
    confidence: 'HIGH' | 'MEDIUM' | 'LOW' | string;
    recommendation: 'MAINTAIN' | 'REDUCE_LOAD' | 'BOOST_AVAILABLE' | string;
    reason: string;
    overloadedDays: number;
}

export function interpretCapacity(input: CapacityInterpretationInput): Interpretation {
    const realized = Math.round(input.realization * 100);
    const whatChanged = `${realized}% realized · ${input.overloadedDays} overloaded day${input.overloadedDays === 1 ? '' : 's'}.`;

    const whyItMatters = input.reason !== ''
        ? input.reason
        : (input.overloadedDays > 0
            ? 'Scheduled minutes exceed available capacity on some days.'
            : 'Load fits available capacity.');

    const whatToDo = (() => {
        switch (input.recommendation) {
            case 'REDUCE_LOAD':
                return 'Reduce the next load — review the schedule and protect recovery.';
            case 'BOOST_AVAILABLE':
                return 'Spare capacity exists — take on backlog or a boost.';
            case 'MAINTAIN':
                return 'Keep the current load.';
            default:
                return 'Review the schedule before committing more load.';
        }
    })();

    void input.confidence;
    return { whatChanged, whyItMatters, whatToDo };
}

export interface PillarInterpretationInput {
    label: string;
    percent: number | null;
}

export function interpretPillars(pillars: PillarInterpretationInput[]): Interpretation {
    const targeted = pillars.filter((p) => p.percent !== null);
    if (targeted.length === 0) {
        return {
            whatChanged: 'No pillar has a target yet.',
            whyItMatters: 'Nothing to balance without mapped targets.',
            whatToDo: 'Map programs to pillars so balance can be measured.',
        };
    }
    const lowest = targeted.reduce((a, b) => (b.percent! < a.percent! ? b : a));
    const percent = Math.round(lowest.percent! * 100);
    return {
        whatChanged: `${lowest.label} sits at ${percent}% of its target — the lowest pillar.`,
        whyItMatters: 'An under-served pillar unbalances long-horizon progress.',
        whatToDo: `Protect time for ${lowest.label} in the coming days.`,
    };
}

export interface HeatmapInterpretationInput {
    totalProductiveMinutes: number;
    activeDays: number;
}

export interface DayInterpretationInput {
    totalDays: number;
}

export function interpretHeatmap(input: HeatmapInterpretationInput): Interpretation {
    const hours = Math.round((input.totalProductiveMinutes / 60) * 10) / 10;
    return {
        whatChanged: `${hours}h focus across ${input.activeDays} active day${input.activeDays === 1 ? '' : 's'} in the period.`,
        whyItMatters: 'Consistency across days compounds — bursts alone leave progress fragile.',
        whatToDo: input.activeDays >= 3
            ? 'Keep the streak; protect the busiest days.'
            : 'Spread focus across more days to build a stable rhythm.',
    };
}

export function interpretDays(input: DayInterpretationInput): Interpretation {
    return {
        whatChanged: `${input.totalDays} tracked day${input.totalDays === 1 ? '' : 's'} in this period.`,
        whyItMatters: 'Daily detail shows where load and recovery land.',
        whatToDo: 'Spot a persistent pattern (for example late-day recharge) before adjusting the schedule.',
    };
}

/**
 * Executive signal (TASK-P17-018, design.md §37 "not 20 graphs immediately",
 * ui-audit UX-C6): the single most decision-relevant statement, resolved by a
 * deterministic priority order — deadline risk beats overload beats
 * imbalance. The action routes to the workflow that resolves the signal.
 */
export type SignalSeverity = 'danger' | 'warn' | 'ok';

export interface ExecutiveSignal {
    severity: SignalSeverity;
    headline: string;
    /** Shell view the resolving action navigates to, if any. */
    action?: 'goals' | 'schedule';
    actionLabel?: string;
}

export interface ExecutiveSignalInput {
    overdue: number;
    atRisk: number;
    overloadedDays: number;
    band: WorkLifeInterpretationInput['band'];
}export function executiveSignal(input: ExecutiveSignalInput): ExecutiveSignal {
    if (input.overdue > 0) {
        return {
            severity: 'danger',
            headline: `${input.overdue} goal${input.overdue > 1 ? 's are' : ' is'} overdue — deadlines are slipping.`,
            action: 'goals',
            actionLabel: 'Review goal',
        };
    }
    if (input.atRisk > 0) {
        return {
            severity: 'warn',
            headline: `${input.atRisk} goal${input.atRisk > 1 ? 's' : ''} at risk of missing the deadline.`,
            action: 'goals',
            actionLabel: 'Review goal',
        };
    }
    if (input.overloadedDays > 0) {
        return {
            severity: 'warn',
            headline: `${input.overloadedDays} overloaded day${input.overloadedDays > 1 ? 's' : ''} — scheduled load exceeds capacity.`,
            action: 'schedule',
            actionLabel: 'Review schedule',
        };
    }
    if (input.band === 'work_leaning') {
        return { severity: 'warn', headline: 'Work-heavy period — recovery time is thin.' };
    }
    return { severity: 'ok', headline: 'No pressing risks — pace and balance look sustainable.' };
}

/** Below half of scheduled tasks completed in the period, load is too heavy. */
export const LOW_COMPLETION_THRESHOLD = 0.5;

export interface ExecutionInterpretationInput {
    completionRate: number;
    completedTasks: number;
    totalTasks: number;
}

export function executionIsLow(input: ExecutionInterpretationInput): boolean {
    return input.totalTasks > 0 && input.completionRate < LOW_COMPLETION_THRESHOLD;
}

export function interpretExecution(input: ExecutionInterpretationInput): Interpretation {
    const rate = Math.round(input.completionRate * 100);
    const whatChanged = `${input.completedTasks}/${input.totalTasks} tasks completed (${rate}%) in the period.`;
    const low = executionIsLow(input);
    return {
        whatChanged,
        whyItMatters: low
            ? 'Completion below half of what was scheduled — planned load outruns real throughput.'
            : 'Throughput matches the plan — the schedule is realistic.',
        whatToDo: low
            ? 'Reduce workload — move lower-priority tasks out of the period.'
            : 'Keep completing scheduled tasks at this pace.',
    };
}