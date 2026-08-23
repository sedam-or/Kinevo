import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import WhyThis from '../WhyThis.vue';

const baseTask = { priority_tier: 2, due_at: null, estimated_minutes: 45 };
const baseAssignment = { start_at: '2026-08-23T09:00:00', duration_minutes: 45, locked: false };

afterEach(() => {
    vi.useRealTimers();
});

describe('WhyThis (TASK-P17-015, FR-63)', () => {
    it('renders collapsed by default — cards stay uncluttered', () => {
        const wrapper = mount(WhyThis, { props: { task: baseTask, assignment: baseAssignment } });
        expect(wrapper.find('[data-testid="why-this-toggle"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="why-this-content"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="why-this-toggle"]').attributes('aria-expanded')).toBe('false');
    });

    it('expands to tier, deadline and slot-fit lines', async () => {
        const wrapper = mount(WhyThis, {
            props: {
                task: { ...baseTask, estimated_minutes: 45 },
                assignment: baseAssignment,
            },
        });
        await wrapper.find('[data-testid="why-this-toggle"]').trigger('click');
        expect(wrapper.find('[data-testid="why-this-content"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="why-tier"]').text()).toContain('P2');
        expect(wrapper.find('[data-testid="why-deadline"]').text()).toContain('No deadline pressure');
        // Slot fit: 45m slot matches the 45m estimate.
        expect(wrapper.find('[data-testid="why-slot"]').text()).toContain('45m slot that matches its 45m estimate');
    });

    it('explains deadline proximity relative to today', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-23T09:00:00'));
        const due = new Date('2026-08-25T17:00:00');
        const wrapper = mount(WhyThis, {
            props: {
                task: { priority_tier: 1, due_at: due.toISOString(), estimated_minutes: null },
                assignment: baseAssignment,
            },
        });
        await wrapper.find('[data-testid="why-this-toggle"]').trigger('click');
        expect(wrapper.find('[data-testid="why-deadline"]').text()).toContain('day(s) away');
    });

    it('explains locked anchors instead of slot fit', async () => {
        const wrapper = mount(WhyThis, {
            props: { task: baseTask, assignment: { ...baseAssignment, locked: true } },
        });
        await wrapper.find('[data-testid="why-this-toggle"]').trigger('click');
        expect(wrapper.find('[data-testid="why-slot"]').text()).toContain('Locked anchor');
    });

    it('shows the energy note only when provided', async () => {
        const wrapper = mount(WhyThis, {
            props: { task: baseTask, assignment: baseAssignment, energyNote: 'Energy 7/10 informed ordering.' },
        });
        await wrapper.find('[data-testid="why-this-toggle"]').trigger('click');
        expect(wrapper.find('[data-testid="why-energy"]').exists()).toBe(true);

        const bare = mount(WhyThis, { props: { task: baseTask, assignment: baseAssignment } });
        await bare.find('[data-testid="why-this-toggle"]').trigger('click');
        expect(bare.find('[data-testid="why-energy"]').exists()).toBe(false);
    });

    it('collapses again on second toggle', async () => {
        const wrapper = mount(WhyThis, { props: { task: baseTask, assignment: baseAssignment } });
        const toggle = wrapper.find('[data-testid="why-this-toggle"]');
        await toggle.trigger('click');
        expect(wrapper.find('[data-testid="why-this-content"]').exists()).toBe(true);
        await toggle.trigger('click');
        expect(wrapper.find('[data-testid="why-this-content"]').exists()).toBe(false);
    });
});
