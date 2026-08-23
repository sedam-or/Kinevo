import { beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FeatureHelp from '../FeatureHelp.vue';

describe('FeatureHelp (TASK-P17-008)', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    function mountHelp() {
        return mount(FeatureHelp, {
            props: {
                id: 'hard-landscape',
                title: 'Hard Landscape',
                body: 'Fixed commitments that block the timeline.',
            },
        });
    }

    it('shows the info trigger and opens the explanation on first use', async () => {
        const wrapper = mountHelp();

        const trigger = wrapper.find('[data-testid="feature-help-hard-landscape-trigger"]');
        expect(trigger.exists()).toBe(true);
        expect(trigger.attributes('aria-expanded')).toBe('false');

        await trigger.trigger('click');
        const panel = wrapper.find('[data-testid="feature-help-hard-landscape-panel"]');
        expect(panel.exists()).toBe(true);
        expect(panel.text()).toContain('Hard Landscape');
        expect(panel.text()).toContain('Fixed commitments that block the timeline.');
        expect(trigger.attributes('aria-expanded')).toBe('true');
    });

    it("dismisses via Got it and never repeats once dismissed", async () => {
        const wrapper = mountHelp();
        await wrapper.find('[data-testid="feature-help-hard-landscape-trigger"]').trigger('click');
        await wrapper.find('[data-testid="feature-help-hard-landscape-dismiss"]').trigger('click');

        expect(wrapper.find('[data-testid="feature-help-hard-landscape"]').exists()).toBe(false);
        expect(localStorage.getItem('kinevo.feature-help.hard-landscape')).toBe('1');

        // Remount = next visit: still gone.
        const again = mountHelp();
        expect(again.find('[data-testid="feature-help-hard-landscape-trigger"]').exists()).toBe(false);
    });

    it('escape closes without dismissing', async () => {
        const wrapper = mountHelp();
        await wrapper.find('[data-testid="feature-help-hard-landscape-trigger"]').trigger('click');
        expect(wrapper.find('[data-testid="feature-help-hard-landscape-panel"]').exists()).toBe(true);

        await wrapper.find('[data-testid="feature-help-hard-landscape-trigger"]').trigger('keydown.escape');
        expect(wrapper.find('[data-testid="feature-help-hard-landscape-panel"]').exists()).toBe(false);
        expect(localStorage.getItem('kinevo.feature-help.hard-landscape')).toBeNull();
        // Still offered next time.
        expect(wrapper.find('[data-testid="feature-help-hard-landscape-trigger"]').exists()).toBe(true);
    });
});
