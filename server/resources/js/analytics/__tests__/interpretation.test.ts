import { describe, expect, it } from 'vitest';
import {
    executiveSignal,
    executionIsLow,
    interpretCapacity,
    interpretDays,
    interpretExecution,
    interpretGoals,
    interpretHeatmap,
    interpretPillars,
    interpretWorkLife,
} from '../interpretation';

describe('interpretExecution', () => {
    it('flags low completion and routes to reducing workload', () => {
        expect(executionIsLow({ completionRate: 0.4, completedTasks: 2, totalTasks: 5 })).toBe(true);
        const result = interpretExecution({ completionRate: 0.4, completedTasks: 2, totalTasks: 5 });
        expect(result.whatChanged).toContain('2/5 tasks completed (40%)');
        expect(result.whyItMatters).toContain('planned load outruns real throughput');
        expect(result.whatToDo).toContain('Reduce workload');
    });

    it('reassures when throughput matches the plan', () => {
        expect(executionIsLow({ completionRate: 0.8, completedTasks: 8, totalTasks: 10 })).toBe(false);
        expect(executionIsLow({ completionRate: 0, completedTasks: 0, totalTasks: 0 })).toBe(false);
        const result = interpretExecution({ completionRate: 0.8, completedTasks: 8, totalTasks: 10 });
        expect(result.whatToDo).toContain('Keep completing scheduled tasks');
    });
});

describe('executiveSignal', () => {
    it('ranks overdue deadlines above every other signal', () => {
        const result = executiveSignal({ overdue: 1, atRisk: 2, overloadedDays: 3, band: 'work_leaning' });
        expect(result.severity).toBe('danger');
        expect(result.headline).toContain('1 goal is overdue');
        expect(result.action).toBe('goals');
    });

    it('surfaces at-risk goals with a review action', () => {
        const result = executiveSignal({ overdue: 0, atRisk: 2, overloadedDays: 1, band: 'balanced' });
        expect(result.severity).toBe('warn');
        expect(result.headline).toContain('2 goals at risk');
        expect(result.action).toBe('goals');
    });

    it('routes overload to the schedule workflow', () => {
        const result = executiveSignal({ overdue: 0, atRisk: 0, overloadedDays: 2, band: 'balanced' });
        expect(result.severity).toBe('warn');
        expect(result.headline).toContain('2 overloaded days');
        expect(result.action).toBe('schedule');
        expect(result.actionLabel).toContain('Review schedule');
    });

    it('warns on a work-heavy imbalance without an action', () => {
        const result = executiveSignal({ overdue: 0, atRisk: 0, overloadedDays: 0, band: 'work_leaning' });
        expect(result.severity).toBe('warn');
        expect(result.headline).toContain('recovery time is thin');
        expect(result.action).toBeUndefined();
    });

    it('reports all-clear when nothing presses', () => {
        const result = executiveSignal({ overdue: 0, atRisk: 0, overloadedDays: 0, band: 'balanced' });
        expect(result.severity).toBe('ok');
        expect(result.headline).toContain('No pressing risks');
    });
});

describe('interpretWorkLife', () => {
    it('compares against the previous period when available', () => {
        const result = interpretWorkLife({
            workRatio: 0.75,
            previous: { work_ratio: 0.6 },
            band: 'work_leaning',
        });
        expect(result.whatChanged).toContain('75% vs 60%');
        expect(result.whyItMatters).toContain('Work outweighs recharge');
        expect(result.whatToDo).toContain('recharge block');
    });

    it('notes a missing baseline period', () => {
        const result = interpretWorkLife({
            workRatio: 0.75,
            previous: null,
            band: 'balanced',
        });
        expect(result.whatChanged).toContain('no earlier period');
        expect(result.whatToDo).toContain('Keep the current rhythm');
    });
});

describe('interpretGoals', () => {
    it('surfaces deadline pressure when goals are at risk', () => {
        const result = interpretGoals({
            completionRate: 0.4,
            totalMilestones: 5,
            completedMilestones: 2,
            overdue: 0,
            atRisk: 2,
        });
        expect(result.whatChanged).toContain('2/5 milestones · 40%');
        expect(result.whyItMatters).toContain('2 goals are at risk');
        expect(result.whatToDo).toContain('Review the at-risk goal');
    });

    it('reassures when nothing presses a deadline', () => {
        const result = interpretGoals({
            completionRate: 0.4,
            totalMilestones: 5,
            completedMilestones: 2,
            overdue: 0,
            atRisk: 0,
        });
        expect(result.whyItMatters).toContain('No goal is pressing');
    });
});

describe('interpretCapacity', () => {
    it('maps an overload to a reduce-load action', () => {
        const result = interpretCapacity({
            realization: 0.7,
            confidence: 'MEDIUM',
            recommendation: 'REDUCE_LOAD',
            reason: 'Overloaded days risk missed load.',
            overloadedDays: 2,
        });
        expect(result.whatChanged).toContain('70% realized · 2 overloaded days');
        expect(result.whyItMatters).toContain('Overloaded days risk missed load');
        expect(result.whatToDo).toContain('Reduce the next load');
    });

    it('offers boost when capacity is unused', () => {
        const result = interpretCapacity({
            realization: 0.8,
            confidence: 'HIGH',
            recommendation: 'BOOST_AVAILABLE',
            reason: 'Realization exceeds 90% without burnout signal.',
            overloadedDays: 0,
        });
        expect(result.whatToDo).toContain('Spare capacity exists');
    });
});

describe('interpretPillars', () => {
    it('calls out the lowest targeted pillar', () => {
        const result = interpretPillars([
            { label: 'Karier', percent: 0.5 },
            { label: 'Kesehatan', percent: null },
            { label: 'Bahasa', percent: 0.1 },
        ]);
        expect(result.whatChanged).toContain('Bahasa sits at 10% of its target');
        expect(result.whatToDo).toContain('Protect time for Bahasa');
    });

    it('handles a pillar set without targets', () => {
        const result = interpretPillars([
            { label: 'Karier', percent: null },
            { label: 'Kesehatan', percent: null },
        ]);
        expect(result.whatToDo).toContain('Map programs to pillars');
    });
});

describe('interpretHeatmap', () => {
    it('reads consistency across active days', () => {
        const result = interpretHeatmap({
            totalProductiveMinutes: 240,
            activeDays: 4,
        });
        expect(result.whatChanged).toContain('4h focus across 4 active days');
        expect(result.whatToDo).toContain('Keep the streak; protect the busiest days');
    });

    it('nudges toward a steadier spread', () => {
        const result = interpretHeatmap({
            totalProductiveMinutes: 240,
            activeDays: 1,
        });
        expect(result.whatToDo).toContain('Spread focus across more days');
    });
});

describe('interpretDays', () => {
    it('summarizes the per-day breakdown', () => {
        const result = interpretDays({ totalDays: 7 });
        expect(result.whatChanged).toContain('7 tracked days');
        expect(result.whatToDo).toContain('Spot a persistent pattern');
    });
});