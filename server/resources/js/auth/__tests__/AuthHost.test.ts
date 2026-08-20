import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../client', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../client')>();
    return {
        ...actual,
        authApi: {
            register: vi.fn(),
            login: vi.fn(),
            logout: vi.fn(),
            me: vi.fn(),
            profile: vi.fn(),
            updateProfile: vi.fn(),
        },
    };
});

// The canvas React island is never loaded/transformed in the test environment
// (Excalidraw needs WebGL/canvas; jsx:preserve is a build-time concern).
vi.mock('../../canvas/react/ExcalidrawIsland', () => ({
    ExcalidrawIsland: {},
}));

import AuthHost from '../AuthHost.vue';
import { useShellStore } from '../../shell/store';
import { authApi } from '../client';
import type { User } from '../types';

const user: User = { id: 1, name: 'Kinevo', email: 'kinevo@example.com' };

beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
});

describe('AuthHost', () => {
    it('shows the login gate for a guest after restore', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        vi.mocked(authApi.me).mockRejectedValue({ status: 401 });

        const wrapper = mount(AuthHost, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="auth-gate"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="login-form"]').exists()).toBe(true);
    });

    it('shows the shell with the user for an authenticated session', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        localStorage.setItem('kinevo.auth.token', 'tok');
        vi.mocked(authApi.me).mockResolvedValue({ user });

        const wrapper = mount(AuthHost, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="auth-user"]').text()).toContain('Kinevo');
        expect(wrapper.find('[data-testid="desktop-nav"]').exists()).toBe(true);
    });

    it('logs out and returns to the guest gate', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        localStorage.setItem('kinevo.auth.token', 'tok');
        vi.mocked(authApi.me).mockResolvedValue({ user });
        vi.mocked(authApi.logout).mockResolvedValue({ message: 'Logged out.' });

        const wrapper = mount(AuthHost, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="logout"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="auth-gate"]').exists()).toBe(true);
    });

    it('switches to the register view from the login gate', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        vi.mocked(authApi.me).mockRejectedValue({ status: 401 });

        const wrapper = mount(AuthHost, { global: { plugins: [pinia] } });
        await flushPromises();

        const buttons = wrapper.findAll('button');
        const registerButton = buttons.find((b) => b.text().includes('Register'));
        expect(registerButton).toBeDefined();
        await registerButton!.trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="register-form"]').exists()).toBe(true);
    });

    it('shows the profile form on the settings view', async () => {
        const pinia = createPinia();
        setActivePinia(pinia);
        localStorage.setItem('kinevo.auth.token', 'tok');
        vi.mocked(authApi.me).mockResolvedValue({ user });
        vi.mocked(authApi.profile).mockResolvedValue({
            profile: {
                user_id: 1,
                display_name: 'Kinevo',
                locale: 'en',
                timezone: 'UTC',
                week_start_day: 'monday',
            },
        });

        const wrapper = mount(AuthHost, { global: { plugins: [pinia] } });
        await flushPromises();

        const shell = useShellStore();
        shell.setView('settings');
        await flushPromises();

        expect(wrapper.find('[data-testid="profile-form"]').exists()).toBe(true);
    });
});
