import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', () => ({
    workspaceApi: {
        list: vi.fn(),
        create: vi.fn(),
        update: vi.fn(),
        archive: vi.fn(),
        restore: vi.fn(),
        setDefault: vi.fn(),
    },
}));

import WorkspaceSwitcher from '../WorkspaceSwitcher.vue';
import { useWorkspaceStore } from '../store';
import { workspaceApi } from '../api';

const personal = { id: 1, user_id: 1, name: 'Personal', slug: 'personal', description: null, icon: null, accent: null, type: 'personal' as const, is_default: true, status: 'active' as const };
const research = { id: 2, user_id: 1, name: 'Research', slug: 'research', description: null, icon: null, accent: '#4f46e5', type: 'research' as const, is_default: false, status: 'active' as const };

describe('WorkspaceSwitcher (TASK-P19-005/006)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.localStorage.clear();
        setActivePinia(createPinia());
        vi.mocked(workspaceApi.list).mockResolvedValue({
            workspaces: [personal, research],
            default_workspace_id: 1,
        });
    });

    function mountSwitcher() {
        return mount(WorkspaceSwitcher, {
            global: { plugins: [createPinia()] },
        });
    }

    it('shows the current workspace and excludes archived ones from options', async () => {
        const wrapper = mountSwitcher();
        await flushPromises();

        expect(wrapper.find('[data-testid="workspace-current-name"]').text()).toBe('Personal');
        await wrapper.find('[data-testid="workspace-switcher-trigger"]').trigger('click');
        expect(wrapper.find('[data-testid="workspace-option-personal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="workspace-option-research"]').exists()).toBe(true);
    });

    it('switches on selection, marks the active row, and persists the choice', async () => {
        const wrapper = mountSwitcher();
        await flushPromises();

        await wrapper.find('[data-testid="workspace-switcher-trigger"]').trigger('click');
        await wrapper.find('[data-testid="workspace-option-research"]').trigger('click');

        expect(wrapper.find('[data-testid="workspace-current-name"]').text()).toBe('Research');
        expect(window.localStorage.getItem('kinevo.active-workspace')).toBe('2');
        // Menu closes after a choice.
        expect(wrapper.find('[data-testid="workspace-switcher-menu"]').exists()).toBe(false);
    });

    it('restores the persisted choice across reloads instead of the server default', async () => {
        window.localStorage.setItem('kinevo.active-workspace', '2');
        const wrapper = mountSwitcher();
        await flushPromises();

        expect(wrapper.find('[data-testid="workspace-current-name"]').text()).toBe('Research');
    });

    it('an unknown stored id falls back to the server default', async () => {
        window.localStorage.setItem('kinevo.active-workspace', '999');
        const wrapper = mountSwitcher();
        await flushPromises();

        expect(wrapper.find('[data-testid="workspace-current-name"]').text()).toBe('Personal');
    });

    it('escape closes the open menu', async () => {
        const wrapper = mountSwitcher();
        await flushPromises();
        await wrapper.find('[data-testid="workspace-switcher-trigger"]').trigger('click');
        expect(wrapper.find('[data-testid="workspace-switcher-menu"]').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();
        expect(wrapper.find('[data-testid="workspace-switcher-menu"]').exists()).toBe(false);
    });
});

describe('workspace store lifecycle (TASK-P19-010)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.localStorage.clear();
        setActivePinia(createPinia());
        vi.mocked(workspaceApi.list).mockResolvedValue({
            workspaces: [personal],
            default_workspace_id: 1,
        });
    });

    it('archive removes from switcher and moves the active selection back to the default', async () => {
        const store = useWorkspaceStore();
        await store.load();

        vi.mocked(workspaceApi.create).mockResolvedValue({ workspace: research });
        await store.create({ name: 'Research' });
        store.switchTo(research.id);
        expect(store.activeWorkspaceId).toBe(research.id);

        vi.mocked(workspaceApi.archive).mockResolvedValue({ workspace: { ...research, status: 'archived' } });
        const ok = await store.archive(research.id);
        expect(ok).toBe(true);
        expect(store.workspaces.some((w) => w.id === research.id)).toBe(false);
        expect(store.archived.some((w) => w.id === research.id)).toBe(true);
        // Active fell back to the default.
        expect(store.activeWorkspaceId).toBe(personal.id);
    });

    it('restore brings an archived workspace back to active', async () => {
        const store = useWorkspaceStore();
        await store.load();
        store.archived.push({ ...research, status: 'archived' });

        vi.mocked(workspaceApi.restore).mockResolvedValue({ workspace: research });
        const ok = await store.restore(research.id);
        expect(ok).toBe(true);
        expect(store.archived.some((w) => w.id === research.id)).toBe(false);
        expect(store.workspaces.some((w) => w.id === research.id && w.status === 'active')).toBe(true);
    });

    it('setDefault updates exactly one flag locally after the server confirms', async () => {
        const store = useWorkspaceStore();
        await store.load();
        vi.mocked(workspaceApi.create).mockResolvedValue({ workspace: research });
        await store.create({ name: 'Research' });

        vi.mocked(workspaceApi.setDefault).mockResolvedValue({ workspace: { ...research, is_default: true } });
        await store.setDefault(research.id);

        expect(store.workspaces.find((w) => w.id === personal.id)?.is_default).toBe(false);
        expect(store.workspaces.find((w) => w.id === research.id)?.is_default).toBe(true);
        expect(store.serverDefaultId).toBe(research.id);
    });
});
