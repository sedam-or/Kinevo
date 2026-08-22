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

    it('marks the active route unmistakably on desktop via aria-current', async () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });
        const todayLink = wrapper.find('[data-testid="nav-today"]');
        expect(todayLink.attributes('aria-current')).toBe('page');

        const settingsLink = wrapper.find('[data-testid="nav-settings"]');
        await settingsLink.trigger('click');
        expect(settingsLink.attributes('aria-current')).toBe('page');
        // Previously active view loses its marker.
        expect(wrapper.find('[data-testid="nav-today"]').attributes('aria-current')).toBeUndefined();
        // Current context is visible in the topbar breadcrumb.
        const section = wrapper.find('[data-testid="current-section"]');
        expect(section.text()).toContain('Settings');
    });

    it('mobile bottom nav shows only the primary subset (design.md §8.3)', () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });
        const mobileNav = wrapper.find('[data-testid="mobile-nav"]');
        // Primary views are pinned with `mobile-nav-<key>` testids.
        for (const key of ['today', 'tasks', 'goals', 'knowledge']) {
            expect(mobileNav.find(`[data-testid="mobile-nav-${key}"]`).exists()).toBe(true);
        }
        // Secondary views are NOT pinned to the fixed bar (no nav-* link there).
        const pinned = wrapper.findAll('[data-testid="mobile-nav"] [data-testid^="nav-"]');
        expect(pinned.length).toBe(0);
        expect(wrapper.find('[data-testid="mobile-more-toggle"]').exists()).toBe(true);
    });

    it('opens the More drawer, lists secondary views, and selects one', async () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });
        const shell = useShellStore();
        const toggle = wrapper.find('[data-testid="mobile-more-toggle"]');
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        const drawer = wrapper.find('[data-testid="mobile-more-drawer"]');
        expect(drawer.exists()).toBe(true);

        const canvas = drawer.find('[data-testid="more-canvas"]');
        expect(canvas.exists()).toBe(true);
        await canvas.trigger('click');
        expect(shell.activeView).toBe('canvas');
        // Selecting a drawer item closes the drawer.
        expect(wrapper.find('[data-testid="mobile-more-drawer"]').exists()).toBe(false);
    });

    it('closes the More drawer via the Close button', async () => {
        const wrapper = mount(AppShell, { global: { plugins: [pinia] } });
        await wrapper.find('[data-testid="mobile-more-toggle"]').trigger('click');
        expect(wrapper.find('[data-testid="mobile-more-drawer"]').exists()).toBe(true);
        await wrapper.find('[data-testid="mobile-more-close"]').trigger('click');
        expect(wrapper.find('[data-testid="mobile-more-drawer"]').exists()).toBe(false);
    });
});
