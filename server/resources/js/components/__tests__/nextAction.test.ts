import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import NextActionBanner from '../NextActionBanner.vue';
import {
    resolveCanvasNextAction,
    resolveGoalNextAction,
    resolveTaskNextAction,
} from '../../next-action';

describe('next-action resolver (TASK-P17-016)', () => {
    it('goal with a pending AI proposal → review proposal', () => {
        const action = resolveGoalNextAction({ milestoneCount: 0, hasPendingProposal: true });
        expect(action?.id).toBe('review-proposal');
    });

    it('goal without milestones → create the first milestone', () => {
        const action = resolveGoalNextAction({ milestoneCount: 0, hasPendingProposal: false });
        expect(action?.id).toBe('create-milestone');
    });

    it('milestone → work on the first open milestone', () => {
        const action = resolveGoalNextAction({
            milestoneCount: 2,
            hasPendingProposal: false,
            openMilestoneTitle: 'Literature Review',
        });
        expect(action?.id).toBe('work-on-milestone');
        expect(action?.label).toContain('Literature Review');
    });

    it('fully-planned goal with no open milestone resolves to no action', () => {
        expect(
            resolveGoalNextAction({ milestoneCount: 3, hasPendingProposal: false, openMilestoneTitle: null }),
        ).toBeNull();
    });

    it('backlog task → schedule it', () => {
        expect(resolveTaskNextAction('backlog')?.id).toBe('schedule-task');
    });

    it('scheduled task → start it', () => {
        expect(resolveTaskNextAction('scheduled')?.id).toBe('start-task');
    });

    it('missed task → recover it', () => {
        expect(resolveTaskNextAction('missed')?.id).toBe('recover-task');
    });

    it('terminal task states surface no next action', () => {
        expect(resolveTaskNextAction('completed')).toBeNull();
        expect(resolveTaskNextAction('skipped')).toBeNull();
    });

    it('canvas offline/queued → view sync status', () => {
        expect(resolveCanvasNextAction('offline')?.id).toBe('view-sync');
        expect(resolveCanvasNextAction('queued')?.id).toBe('view-sync');
        expect(resolveCanvasNextAction('saved')).toBeNull();
    });
});

describe('NextActionBanner (TASK-P17-016)', () => {
    it('renders label and emits the action id on click', async () => {
        const wrapper = mount(NextActionBanner, {
            props: { action: { id: 'start-task', label: 'Scheduled — start it from Today.' } },
        });
        expect(wrapper.find('[data-testid="next-action"]').attributes('data-action')).toBe('start-task');
        await wrapper.find('[data-testid="next-action-button"]').trigger('click');
        expect(wrapper.emitted('act')?.[0]).toEqual(['start-task']);
    });
});
