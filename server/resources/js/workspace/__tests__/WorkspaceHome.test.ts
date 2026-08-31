import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', () => ({
    workspaceApi: {
        list: vi.fn().mockResolvedValue({ workspaces: [
            { id: 1, user_id: 1, name: 'Research', slug: 'research', description: 'Deep work', icon: null, accent: '#4f46e5', type: 'research', is_default: false, status: 'active' },
        ], default_workspace_id: 1 }),
        create: vi.fn(), update: vi.fn(), archive: vi.fn(), restore: vi.fn(), setDefault: vi.fn(),
    },
}));
vi.mock('../../goal/api', () => ({
    goalApi: {
        goals: vi.fn().mockResolvedValue({ goals: [{ id: 7, title: 'Finish thesis', progress: 40, status: 'active' }] }),
        programs: vi.fn().mockResolvedValue({ programs: [] }),
    },
}));

import WorkspaceHome from '../WorkspaceHome.vue';

describe('WorkspaceHome (TASK-P19-038)', () => {
    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
    });

    it('orders sections Identity → Current Goal → Today → doorways (IA contract)', async () => {
        const wrapper = mount(WorkspaceHome, { global: { plugins: [createPinia()] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="wh-name"]').text()).toContain('Research');
        // P28-010: the workspace explanation control lives with the identity.
        expect(wrapper.find('[data-testid="feature-help-workspace"]').exists()).toBe(true);
        // DOM order: identity before current-goal before today before doorways
        const ids = ['wh-identity', 'wh-current-goal', 'wh-today', 'wh-doorways'];
        const els = ids.map((id) => wrapper.find(`[data-testid="${id}"]`).element);
        for (let i = 1; i < els.length; i++) {
            expect(
                els[i - 1].compareDocumentPosition(els[i]) & Node.DOCUMENT_POSITION_FOLLOWING,
                `order of ${ids[i]}`,
            ).toBeTruthy();
        }
        expect(wrapper.find('[data-testid="wh-current-goal"]').text()).toContain('Finish thesis');
        expect(wrapper.find('[data-testid="wh-current-goal"]').text()).toContain('40%');
    });
});
