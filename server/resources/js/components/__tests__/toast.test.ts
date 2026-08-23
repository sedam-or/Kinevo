import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { mount } from '@vue/test-utils';
import { useToastStore } from '../toast';
import ToastHost from '../ToastHost.vue';

describe('toast (TASK-P17-011)', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });
    afterEach(() => {
        vi.useRealTimers();
    });

    it('pushes a message and auto-dismisses it', () => {
        const toast = useToastStore();
        toast.push('Task completed · progress updated');
        expect(toast.items).toHaveLength(1);
        expect(toast.items[0].message).toContain('Task completed');

        vi.advanceTimersByTime(3000);
        expect(toast.items).toHaveLength(0);
    });

    it('renders through ToastHost as a polite live region with manual dismiss', async () => {
        const toast = useToastStore();
        toast.push('Check-in logged');
        const wrapper = mount(ToastHost);

        const host = wrapper.find('[data-testid="toast-host"]');
        expect(host.attributes('aria-live')).toBe('polite');
        expect(host.text()).toContain('Check-in logged');

        await wrapper.find('[class*="text-xs"]').trigger('click');
        expect(wrapper.find('[data-testid="toast-host"]').exists()).toBe(false);
    });
});
