import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        aiApi: {
            ...actual.aiApi,
            proposals: vi.fn(),
            updateProposal: vi.fn(),
            acceptProposal: vi.fn(),
            rejectProposal: vi.fn(),
        },
    };
});

import ProposalReviewCard from '../ProposalReviewCard.vue';
import { aiApi, type AiProposal, type AiProposalPayload } from '../api';

const pendingProposal: AiProposal = {
    id: 7,
    user_id: 1,
    proposal_type: 'goal_breakdown',
    schema_version: 1,
    payload: {
        type: 'goal_breakdown_proposal',
        goal_id: 3,
        rationale: 'Research before build reduces rework.',
        risks: ['Scope creep around integrations.'],
        milestones: [
            { title: 'Research', target_date: '2026-09-01', estimated_minutes: 600 },
            { title: 'Build', estimated_minutes: 1800 },
        ],
    },
    decision: 'pending',
    operation_id: null,
    created_at: '2026-08-22T00:00:00.000Z',
};

describe('ProposalReviewCard (TASK-P17-004)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    function mountCard() {
        return mount(ProposalReviewCard, { props: { goalId: 3 } });
    }

    it('shows nothing when the goal has no pending proposal', async () => {
        vi.mocked(aiApi.proposals).mockResolvedValue({ proposals: [] });
        const wrapper = mountCard();
        await flushPromises();
        expect(wrapper.find('[data-testid="proposal-review"]').exists()).toBe(false);
    });

    it('renders rationale, risks and milestone effort without any editing UI noise', async () => {
        vi.mocked(aiApi.proposals).mockResolvedValue({ proposals: [pendingProposal] });
        const wrapper = mountCard();
        await flushPromises();

        expect(wrapper.find('[data-testid="proposal-review"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Research before build reduces rework.');
        expect(wrapper.text()).toContain('Scope creep around integrations.');
        expect(wrapper.find('[data-testid="proposal-milestone-0"]').text()).toContain('10h');
        expect(wrapper.find('[data-testid="proposal-milestones"]').findAll('li').length).toBe(2);
    });

    it('edits a milestone and saves through the validated contract, marking the proposal edited', async () => {
        vi.mocked(aiApi.proposals).mockResolvedValue({ proposals: [pendingProposal] });
        vi.mocked(aiApi.updateProposal).mockResolvedValue({
            proposal: { ...pendingProposal, decision: 'edited' },
        });
        const wrapper = mountCard();
        await flushPromises();

        await wrapper.find('[data-testid="proposal-edit"]').trigger('click');
        const firstTitle = wrapper.find('[data-testid="proposal-milestone-title-0"]');
        (firstTitle.element as HTMLInputElement).value = 'Discovery sprint';
        await firstTitle.trigger('input');
        await wrapper.find('[data-testid="proposal-save-edits"]').trigger('click');
        await flushPromises();

        expect(aiApi.updateProposal).toHaveBeenCalledWith(7, expect.objectContaining({
            goal_id: 3,
            milestones: [expect.objectContaining({ title: 'Discovery sprint' }), expect.anything()],
        }));
        expect((wrapper.find('[data-testid="proposal-edited-badge"]').element as HTMLElement).textContent.trim()).toBe('edited');
    });

    it('accept applies via the proposal endpoint only and notifies the parent', async () => {
        vi.mocked(aiApi.proposals).mockResolvedValue({ proposals: [pendingProposal] });
        vi.mocked(aiApi.acceptProposal).mockResolvedValue(undefined);
        const wrapper = mountCard();
        await flushPromises();

        await wrapper.find('[data-testid="proposal-accept"]').trigger('click');
        await flushPromises();

        expect(aiApi.acceptProposal).toHaveBeenCalledWith(7);
        expect(wrapper.emitted('accepted')).toHaveLength(1);
        expect(wrapper.find('[data-testid="proposal-review"]').exists()).toBe(false);
    });

    it('reject dismisses the review without touching the domain', async () => {
        vi.mocked(aiApi.proposals).mockResolvedValue({ proposals: [pendingProposal] });
        vi.mocked(aiApi.rejectProposal).mockResolvedValue(undefined);
        const wrapper = mountCard();
        await flushPromises();

        await wrapper.find('[data-testid="proposal-reject"]').trigger('click');
        await flushPromises();

        expect(aiApi.rejectProposal).toHaveBeenCalledWith(7);
        expect(aiApi.acceptProposal).not.toHaveBeenCalled();
        expect(wrapper.emitted('accepted')).toBeUndefined();
        expect(wrapper.find('[data-testid="proposal-review"]').exists()).toBe(false);
    });

    it('renders the explanation block — summary, assumptions, inputs, constraints (TASK-P17-027)', async () => {
        vi.mocked(aiApi.proposals).mockResolvedValue({
            proposals: [{
                ...pendingProposal,
                payload: {
                    ...pendingProposal.payload,
                    assumptions: ['Stable team size', 'Quarterly deadline holds'],
                    inputs: ['Deadline 2026-12-31', 'Weekly capacity 20h'],
                    constraints: ['Hard landscape Monday 09:00', '30% recharge reserve'],
                } as Extract<AiProposalPayload, { type: 'goal_breakdown_proposal' }>,
            }],
        });
        const wrapper = mountCard();
        await flushPromises();

        expect(wrapper.find('[data-testid="proposal-rationale"]').text()).toContain('Research before build reduces rework.');
        expect(wrapper.find('[data-testid="proposal-assumptions"]').text()).toContain('Stable team size');
        expect(wrapper.find('[data-testid="proposal-inputs"]').text()).toContain('Weekly capacity 20h');
        expect(wrapper.find('[data-testid="proposal-constraints"]').text()).toContain('30% recharge reserve');
    });

    it('hides explanation lists when the proposal has none', async () => {
        vi.mocked(aiApi.proposals).mockResolvedValue({ proposals: [pendingProposal] });
        const wrapper = mountCard();
        await flushPromises();

        expect(wrapper.find('[data-testid="proposal-assumptions"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="proposal-inputs"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="proposal-constraints"]').exists()).toBe(false);
    });
});
