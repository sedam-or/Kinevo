import { describe, expect, it, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { mount } from '@vue/test-utils';
import SyncStatusPanel from '../SyncStatusPanel.vue';
import { useShellStore } from '../store';

describe('SyncStatusPanel', () => {
    let pinia: ReturnType<typeof createPinia>;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
    });

    it('renders the current sync state badge with an explanation', () => {
        const shell = useShellStore();
        shell.setSyncState('queued');
        shell.setSyncQueuedCount(2);

        const wrapper = mount(SyncStatusPanel, { global: { plugins: [pinia] } });

        expect(wrapper.find('[data-testid="sync-status-badge"]').text()).toContain('Queued');
        expect(wrapper.find('[data-testid="sync-explanation"]').text()).toContain('waiting to sync');
        expect(wrapper.find('[data-testid="sync-queued-count"]').text()).toContain('2 queued');
        expect(wrapper.find('[data-testid="sync-retry"]').exists()).toBe(false);
    });

    it('shows the retry button for retrying and failed states', async () => {
        const shell = useShellStore();
        shell.setSyncState('retrying');
        shell.setSyncError('network flaky');

        const wrapper = mount(SyncStatusPanel, { global: { plugins: [pinia] } });
        expect(wrapper.find('[data-testid="sync-retry"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="sync-error"]').text()).toContain('network flaky');

        const retry = vi.fn();
        shell.registerRetrySync(retry);
        await wrapper.find('[data-testid="sync-retry"]').trigger('click');
        expect(retry).toHaveBeenCalled();
    });

    it('shows no retry button when the queue is healthy', () => {
        const shell = useShellStore();
        shell.setSyncState('saved');
        const wrapper = mount(SyncStatusPanel, { global: { plugins: [pinia] } });
        expect(wrapper.find('[data-testid="sync-retry"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="sync-error"]').exists()).toBe(false);
    });
});