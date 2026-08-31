/**
 * Feature Definition Registry (TASK-P20-011) — the single source of truth
 * for in-product feature explanations. FeatureHelp/FeatureIntro consume this
 * so the same feature never carries duplicated or drifting help text.
 *
 * Contract per entry: purpose (what), benefit (why it matters), when to use,
 * and where its primary action lives.
 */
export interface FeatureDefinition {
    id: string;
    title: string;
    body: string;
}

export const FEATURE_REGISTRY: Record<string, FeatureDefinition> = {
    'hard-landscape': {
        id: 'hard-landscape',
        title: 'Hard Landscape',
        body: 'Fixed commitments — classes, meetings, shifts. Kinevo never schedules over them; your tasks fill the space around them.',
    },
    'capacity': {
        id: 'capacity',
        title: 'Effective Capacity',
        body: 'What you can realistically complete based on recent weeks, not an ideal day. Kinevo reduces load before you burn out.',
    },
    'adaptive-context': {
        id: 'adaptive-context',
        title: 'Adaptive Context',
        body: 'A quick energy check-in tunes which task fits right now — advisory only, never a diagnosis.',
    },
    'progress-events': {
        id: 'progress-events',
        title: 'Progress Events',
        body: 'Meaningful progress is recorded automatically as you complete work, so analytics stay honest.',
    },
    'dynamic-rescheduler': {
        id: 'dynamic-rescheduler',
        title: 'Dynamic Rescheduler',
        body: 'When reality changes, Kinevo proposes minimal moves — you see before/after with reasons before anything applies.',
    },
    'ai-proposal': {
        id: 'ai-proposal',
        title: 'AI Breakdown Proposal',
        body: 'AI suggests, you decide. Nothing is applied until you accept — edit milestones freely before accepting.',
    },
    'knowledge': {
        id: 'knowledge',
        title: 'Knowledge & Notes',
        body: 'Notes preserve context outside the plan — what you learned, decided, or need to remember. Link them to goals and tasks so thinking stays connected to execution.',
    },
    'canvas': {
        id: 'canvas',
        title: 'Canvas',
        body: 'A visual board for planning and synthesis — map an idea, untangle a problem, or sketch the shape of a project before it becomes tasks.',
    },
    'ai-provider-modes': {
        id: 'ai-provider-modes',
        title: 'AI provider modes',
        body: 'AI runs through a provider you control. Hosted mode uses Kinevo\u2019s included AI allowance. BYOK connects your own provider key and never touches your hosted credits. \u201cNot configured\u201d or \u201cUnavailable\u201d means AI actions pause until a provider is reachable.',
    },
    'workspace': {
        id: 'workspace',
        title: 'Workspaces',
        body: 'Keep life areas separate — Research, Work, Personal — while Today still shows everything scheduled.',
    },
    'recovery': {
        id: 'recovery',
        title: 'Morning Recovery',
        body: 'Missed yesterday? Recover tasks this morning without guilt — reschedule, complete, or keep them in backlog.',
    },
};

export function featureDefinition(id: string): FeatureDefinition | undefined {
    return FEATURE_REGISTRY[id];
}
