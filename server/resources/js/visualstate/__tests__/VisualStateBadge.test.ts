import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import VisualStateBadge from '../VisualStateBadge.vue';
import SchedulerExplanation from '../SchedulerExplanation.vue';

describe('VisualStateBadge', () => {
    it('renders the state glyph and label', () => {
        const wrapper = mount(VisualStateBadge, { props: { state: 'locked' } });
        expect(wrapper.find('[data-testid="visual-state-badge"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Locked');
        expect(wrapper.text()).toContain('🔒');
    });

    it('uses a dashed border for non-color-signal states like conflict', () => {
        const wrapper = mount(VisualStateBadge, { props: { state: 'conflict' } });
        const classes = wrapper.find('[data-testid="visual-state-badge"]').classes();
        expect(classes).toContain('border-dashed');
    });

    it('supports an override label', () => {
        const wrapper = mount(VisualStateBadge, { props: { state: 'queued', label: 'Waiting' } });
        expect(wrapper.text()).toContain('Waiting');
    });

    it('sets the data-state attribute', () => {
        const wrapper = mount(VisualStateBadge, { props: { state: 'failed' } });
        expect(wrapper.find('[data-testid="visual-state-badge"]').attributes('data-state')).toBe('failed');
    });
});

describe('SchedulerExplanation', () => {
    it('renders reason codes with labels', () => {
        const wrapper = mount(SchedulerExplanation, {
            props: { codes: ['LOCK_PROTECTED', 'DEADLINE_PRIORITY'] },
        });
        expect(wrapper.findAll('[data-testid="scheduler-reason"]')).toHaveLength(2);
        expect(wrapper.text()).toContain('LOCK_PROTECTED');
        expect(wrapper.text()).toContain('Nearest deadline prioritized');
    });

    it('shows empty state when no known codes', () => {
        const wrapper = mount(SchedulerExplanation, { props: { codes: ['NOPE'] } });
        expect(wrapper.find('[data-testid="scheduler-explanation-empty"]').exists()).toBe(true);
    });
});
