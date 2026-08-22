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
            breakdownProposal: vi.fn(),
            programs: vi.fn(),
            createProgram: vi.fn(),
            setProgramStatus: vi.fn(),
        },
    };
});

import GoalListView from '../GoalListView.vue';
import GoalDetailView from '../GoalDetailView.vue';
import { goalApi } from '../api';
import { useShellStore } from '../../shell/store';
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

    it('creates a goal as a planning workflow and offers an AI breakdown (P17-003)', async () => {
        vi.mocked(goalApi.goals).mockResolvedValue({ goals: [] });
        vi.mocked(goalApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(goalApi.createGoal).mockResolvedValue({ goal: { ...goal, title: 'Write a book' } });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(GoalListView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="goal-create-title"]').setValue('Write a book');
        await wrapper.find('[data-testid="goal-create-description"]').setValue('A 200-page novel');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(goalApi.createGoal).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Write a book', description: 'A 200-page novel' }),
        );
        // Suggestion appears; the goal itself is NOT mutated further.
        expect(wrapper.find('[data-testid="goal-breakdown-suggestion"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="goal-breakdown-ai"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="goal-breakdown-manual"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="goal-breakdown-later"]').exists()).toBe(true);

        // Generate with AI hits the proposal backend (pending, non-mutating).
        vi.mocked(goalApi.breakdownProposal).mockResolvedValue({ proposal: { id: 11, status: 'pending' } });
        await wrapper.find('[data-testid="goal-breakdown-ai"]').trigger('click');
        await flushPromises();
        expect(goalApi.breakdownProposal).toHaveBeenCalledWith(1);
        expect(wrapper.find('[data-testid="goal-proposal-ready"]').exists()).toBe(true);
        // createMilestone was never called — no silent mutation.
        expect(goalApi.createMilestone).not.toHaveBeenCalled();
    });

    it('surfaces the AI breakdown error without navigating away (P17-003)', async () => {
        vi.mocked(goalApi.goals).mockResolvedValue({ goals: [] });
        vi.mocked(goalApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(goalApi.createGoal).mockResolvedValue({ goal });
        vi.mocked(goalApi.breakdownProposal).mockRejectedValue(new Error('AI provider unavailable'));
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(GoalListView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="goal-create-title"]').setValue('Ship v1');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        await wrapper.find('[data-testid="goal-breakdown-ai"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="goal-proposal-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="goal-breakdown-suggestion"]').exists()).toBe(true);
    });

    it('declining with "I\'ll do it myself" opens the goal directly (P17-003)', async () => {
        vi.mocked(goalApi.goals).mockResolvedValue({ goals: [] });
        vi.mocked(goalApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(goalApi.createGoal).mockResolvedValue({ goal });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(GoalListView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="goal-create-title"]').setValue('Ship v1');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        await wrapper.find('[data-testid="goal-breakdown-manual"]').trigger('click');

        expect(wrapper.emitted('selectGoal')?.[0]).toEqual([1]);
        expect(goalApi.breakdownProposal).not.toHaveBeenCalled();
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

    it('renders downstream continuity links (TASK-P17-002)', async () => {
        vi.mocked(goalApi.goal).mockResolvedValue({ goal });
        vi.mocked(goalApi.milestones).mockResolvedValue({ milestones: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(GoalDetailView, { props: { goalId: 1 }, global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="entity-links"]').exists()).toBe(true);
        for (const view of ['tasks', 'schedule', 'analytics']) {
            expect(wrapper.find(`[data-testid="entity-link-${view}"]`).exists()).toBe(true);
        }

        // Chip navigates to the downstream surface.
        const shell = useShellStore();
        await wrapper.find('[data-testid="entity-link-tasks"]').trigger('click');
        expect(shell.activeView).toBe('tasks');
    });
});
