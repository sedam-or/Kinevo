import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

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

import GoalListView from '../GoalListView.vue';
import GoalDetailView from '../GoalDetailView.vue';
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

describe('GoalListView', () => {
    it('loads and lists goals and programs', async () => {
        vi.mocked(goalApi.goals).mockResolvedValue({ goals: [goal] });
        vi.mocked(goalApi.programs).mockResolvedValue({ programs: [program] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(GoalListView, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="goal-item"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="program-item"]').exists()).toBe(true);
    });

    it('emits selectGoal when a goal is opened', async () => {
        vi.mocked(goalApi.goals).mockResolvedValue({ goals: [goal] });
        vi.mocked(goalApi.programs).mockResolvedValue({ programs: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(GoalListView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="goal-open"]').trigger('click');
        expect(wrapper.emitted('selectGoal')?.[0]).toEqual([1]);
    });
});

describe('GoalDetailView', () => {
    it('loads a goal and renders milestones + status actions', async () => {
        vi.mocked(goalApi.goal).mockResolvedValue({ goal });
        vi.mocked(goalApi.milestones).mockResolvedValue({ milestones: [milestone] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(GoalDetailView, { props: { goalId: 1 }, global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="goal-outcome"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="goal-progress"]').text()).toContain('0%');
        expect(wrapper.find('[data-testid="milestone-item"]').exists()).toBe(true);
        // active goal offers paused/completed/archived/dropped
        expect(wrapper.find('[data-testid="goal-to-completed"]').exists()).toBe(true);
    });
});
