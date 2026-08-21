import { describe, expect, it, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { mount } from '@vue/test-utils';
import AppShell from '../AppShell.vue';
import { useShellStore } from '../store';

describe('AppShell', () => {
    let pinia: ReturnType<typeof createPinia>;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
    });

    it('renders the primary navigation on desktop and mobile', () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });
        expect(wrapper.find('[data-testid="desktop-nav"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="mobile-nav"]').exists()).toBe(true);
        const shell = useShellStore();
        expect(shell.navItems.length).toBeGreaterThan(0);
    });

    it('switches the active view when a nav item is selected', async () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });
        const shell = useShellStore();
        const settingsLink = wrapper.find('[data-testid="nav-settings"]');
        expect(settingsLink.exists()).toBe(true);
        await settingsLink.trigger('click');
        expect(shell.activeView).toBe('settings');
    });

    it('shows the error banner when an error is set', async () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });
        const shell = useShellStore();
        shell.setError('something broke');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="error-banner"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="error-banner"]').text()).toContain('something broke');
    });

    it('toggles the theme', async () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });
        const shell = useShellStore();
        const toggle = wrapper.find('[data-testid="theme-toggle"]');
        // Defaults to 'system'; cycling order is light → dark → system.
        await toggle.trigger('click');
        expect(shell.theme).toBe('light');
        await toggle.trigger('click');
        expect(shell.theme).toBe('dark');
    });
});
