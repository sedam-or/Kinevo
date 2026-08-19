import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        goalApi: {
            goals: vi.fn(),
            goal: vi.fn(),
            createGoal: vi.fn(),
            setGoalStatus: vi.fn(),
            milestones: vi.fn(),
            createMilestone: vi.fn(),
            setMilestoneStatus: vi.fn(),
            programs: vi.fn(),
            createProgram: vi.fn(),
            setProgramStatus: vi.fn(),
        },
    };
});

import { useGoalStore } from '../store';
import { goalApi } from '../api';
import type { Goal, Milestone, Program } from '../types';

const goal: Goal = {
    id: 1, user_id: 1, title: 'Ship v1', description: null, horizon: 'yearly',
    start_date: null, target_date: '2026-12-31', target_metric: null,
    status: 'active', priority_tier: 1, progress_mode: 'derived', progress: 0,
};

const program: Program = {
    id: 2, user_id: 1, name: 'Research', description: null, category: null,
    workload_type: 'structured', weekly_target_minutes: 300, min_weekly_minutes: null,
    max_weekly_minutes: null, status: 'active', priority_tier: 2, version: 1,
};

const milestone: Milestone = {
    id: 3, user_id: 1, goal_id: 1, title: 'Beta', description: null, sequence: 1,
    target_date: null, estimated_minutes: 240, status: 'planned', progress_mode: 'derived',
    progress: 0, completed_at: null, version: 1,
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('goal store', () => {
    it('loads goals and programs together', async () => {
        vi.mocked(goalApi.goals).mockResolvedValue({ goals: [goal] });
        vi.mocked(goalApi.programs).mockResolvedValue({ programs: [program] });
        const store = useGoalStore();
        await store.loadAll();
        expect(store.goals).toHaveLength(1);
        expect(store.programs).toHaveLength(1);
    });

    it('loads a goal with its milestones', async () => {
        vi.mocked(goalApi.goal).mockResolvedValue({ goal });
        vi.mocked(goalApi.milestones).mockResolvedValue({ milestones: [milestone] });
        const store = useGoalStore();
        await store.loadGoal(1);
        expect(store.currentGoal?.title).toBe('Ship v1');
        expect(store.milestones).toHaveLength(1);
    });

    it('creates a goal and prepends it', async () => {
        vi.mocked(goalApi.createGoal).mockResolvedValue({ goal });
        const store = useGoalStore();
        const created = await store.createGoal({ title: 'Ship v1', horizon: 'yearly' });
        expect(created?.id).toBe(1);
        expect(store.goals).toHaveLength(1);
    });

    it('applies a goal status transition', async () => {
        vi.mocked(goalApi.setGoalStatus).mockResolvedValue({ goal: { ...goal, status: 'completed' } });
        const store = useGoalStore();
        store.goals = [goal];
        await store.setGoalStatus(1, 'completed');
        expect(store.goals[0].status).toBe('completed');
    });

    it('adds a milestone', async () => {
        vi.mocked(goalApi.createMilestone).mockResolvedValue({ milestone });
        const store = useGoalStore();
        await store.createMilestone(1, { title: 'Beta' });
        expect(store.milestones).toHaveLength(1);
    });
});
