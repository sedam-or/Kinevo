/**
 * Next Action Engine (TASK-P17-016, NFR intuitive progression).
 *
 * Resolves the ONE context-aware next action per object state so surfaces can
 * answer "what should I do next?" instead of presenting equal-weight options.
 * Pure derivation from existing observable state — no scheduling or domain
 * logic lives here.
 */

export interface NextAction {
    /** Stable machine id, used for tests and click routing. */
    id:
        | 'review-proposal'
        | 'create-milestone'
        | 'work-on-milestone'
        | 'schedule-task'
        | 'start-task'
        | 'recover-task'
        | 'view-sync';
    label: string;
}

export interface GoalNextActionInput {
    milestoneCount: number;
    hasPendingProposal: boolean;
    openMilestoneTitle?: string | null;
}

export function resolveGoalNextAction(input: GoalNextActionInput): NextAction | null {
    if (input.hasPendingProposal) {
        return { id: 'review-proposal', label: 'Review the AI proposal before anything changes.' };
    }
    if (input.milestoneCount === 0) {
        return { id: 'create-milestone', label: 'Create the first milestone to make this goal actionable.' };
    }
    if (input.openMilestoneTitle) {
        return { id: 'work-on-milestone', label: `Work on “${input.openMilestoneTitle}” in Today.` };
    }
    return null;
}

export function resolveTaskNextAction(status: string): NextAction | null {
    switch (status) {
        case 'backlog':
            return { id: 'schedule-task', label: 'This task is unscheduled — give it a slot.' };
        case 'scheduled':
            return { id: 'start-task', label: 'Scheduled — start it from Today when its slot arrives.' };
        case 'missed':
            return { id: 'recover-task', label: 'This task was missed — recover it into an upcoming day.' };
        default:
            return null;
    }
}

export type CanvasSaveState = 'saved' | 'saving' | 'queued' | 'offline' | 'conflict' | string;

export function resolveCanvasNextAction(saveState: CanvasSaveState): NextAction | null {
    if (saveState === 'offline' || saveState === 'queued') {
        return { id: 'view-sync', label: 'You are offline — changes are queued and will sync automatically.' };
    }
    return null;
}
