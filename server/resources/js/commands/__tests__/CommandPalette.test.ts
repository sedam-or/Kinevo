import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api/client', () => ({
    apiClient: { request: vi.fn().mockResolvedValue({ results: [] }) },
}));

import CommandPalette from '../CommandPalette.vue';
import { apiClient } from '../../api/client';

describe('CommandPalette (TASK-P20-033)', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.clearAllMocks();
        setActivePinia(createPinia());
    });

    function mountPalette(open: boolean) {
        return mount(CommandPalette, {
            props: { open },
            global: { plugins: [createPinia()] },
            attachTo: document.body,
        });
    }

    it('lists navigation + workspace commands when opened', async () => {
        const wrapper = mountPalette(true);
        await flushPromises();

        expect(wrapper.find('[data-testid="command-palette"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="command-nav-today"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="command-nav-goals"]').exists()).toBe(true);
    });

    it('executes a navigation command and closes (v-model)', async () => {
        const wrapper = mountPalette(true);
        await flushPromises();

        await wrapper.find('[data-testid="command-nav-goals"]').trigger('click');
        expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
        // Shell store navigated.
        const { useShellStore } = await import('../../shell/store');
        expect(useShellStore().activeView).toBe('goals');
        wrapper.unmount();
    });

    it('empty state appears for unmatched queries and knowledge search runs', async () => {
        vi.mocked(apiClient.request).mockResolvedValue({ results: [] });
        const wrapper = mountPalette(true);
        await flushPromises();

        await wrapper.find('[data-testid="command-input"]').setValue('zzz-no-match');
        await new Promise((r) => setTimeout(r, 350));
        await flushPromises();

        expect(wrapper.find('[data-testid="command-empty"]').exists()).toBe(true);
        expect(apiClient.request).toHaveBeenCalledWith(expect.stringContaining('/knowledge/search?q='));
        wrapper.unmount();
    });

    it('Escape emits close', async () => {
        const wrapper = mountPalette(true);
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();
        expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
        wrapper.unmount();
    });
});
