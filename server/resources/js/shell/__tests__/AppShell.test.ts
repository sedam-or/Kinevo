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

    it('provides a skip-to-content link and semantic landmarks', () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });

        const skip = wrapper.find('[data-testid="skip-link"]');
        expect(skip.exists()).toBe(true);
        expect(skip.attributes('href')).toBe('#main-content');
        // Skip link is visually hidden until focused (bypass block target).
        expect(skip.classes()).toContain('sr-only');

        const main = wrapper.find('[data-testid="content-surface"]');
        expect(main.element.tagName).toBe('MAIN');
        expect(main.attributes('id')).toBe('main-content');

        // Distinct aria-labels so both nav landmarks are distinguishable to AT.
        const desktop = wrapper.find('[data-testid="desktop-nav"]');
        const mobile = wrapper.find('[data-testid="mobile-nav"]');
        expect(desktop.attributes('aria-label')).toBe('Primary');
        expect(mobile.attributes('aria-label')).toBe('Primary mobile');
    });
});
