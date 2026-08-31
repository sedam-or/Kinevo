import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

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

import { useAuthStore } from '../store';
import { authApi } from '../client';
import type { Profile, User } from '../types';

const user: User = { id: 1, name: 'Kinevo', email: 'kinevo@example.com' };
const profile: Profile = {
    user_id: 1,
    display_name: 'Kinevo',
    locale: 'en',
    timezone: 'UTC',
    week_start_day: 'monday',
};

beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    vi.clearAllMocks();
});

describe('auth store', () => {
    it('restoreSession marks guest when no token is stored', async () => {
        const auth = useAuthStore();
        await auth.restoreSession();
        expect(auth.status).toBe('guest');
        expect(auth.isAuthenticated).toBe(false);
    });

    it('restoreSession validates an existing token via /auth/me', async () => {
        localStorage.setItem('kinevo.auth.token', 'tok');
        vi.mocked(authApi.me).mockResolvedValue({ user });

        const auth = useAuthStore();
        await auth.restoreSession();

        expect(auth.status).toBe('authenticated');
        expect(auth.user?.id).toBe(1);
    });

    it('restoreSession clears a stale token when /auth/me fails', async () => {
        localStorage.setItem('kinevo.auth.token', 'bad');
        vi.mocked(authApi.me).mockRejectedValue({ status: 401, message: 'Unauthenticated' });

        const auth = useAuthStore();
        await auth.restoreSession();

        expect(auth.status).toBe('guest');
        expect(localStorage.getItem('kinevo.auth.token')).toBeNull();
    });

    it('restoreSession keeps the session on an OFFLINE network failure (ADR-017 §2.16)', async () => {
        localStorage.setItem('kinevo.auth.token', 'good');
        vi.mocked(authApi.me).mockRejectedValue({ status: 0, message: 'offline', retryable: true });

        const auth = useAuthStore();
        await auth.restoreSession();

        expect(auth.status).toBe('unknown');
        expect(localStorage.getItem('kinevo.auth.token')).toBe('good');
    });

    it('login stores the token and user', async () => {
        vi.mocked(authApi.login).mockResolvedValue({ user, token: 'tok' });

        const auth = useAuthStore();
        await auth.login('kinevo@example.com', 'secret');

        expect(auth.isAuthenticated).toBe(true);
        expect(localStorage.getItem('kinevo.auth.token')).toBe('tok');
    });

    it('register stores the user, token, and profile', async () => {
        vi.mocked(authApi.register).mockResolvedValue({ user, token: 'tok', profile });

        const auth = useAuthStore();
        await auth.register('Kinevo', 'kinevo@example.com', 'secret');

        expect(auth.isAuthenticated).toBe(true);
        expect(auth.profile?.timezone).toBe('UTC');
    });

    it('logout clears the session', async () => {
        vi.mocked(authApi.login).mockResolvedValue({ user, token: 'tok' });
        vi.mocked(authApi.logout).mockResolvedValue({ message: 'Logged out.' });

        const auth = useAuthStore();
        await auth.login('kinevo@example.com', 'secret');
        await auth.logout();

        expect(auth.isAuthenticated).toBe(false);
        expect(auth.user).toBeNull();
        expect(localStorage.getItem('kinevo.auth.token')).toBeNull();
    });

    it('loadProfile populates the profile', async () => {
        localStorage.setItem('kinevo.auth.token', 'tok');
        vi.mocked(authApi.me).mockResolvedValue({ user });
        vi.mocked(authApi.profile).mockResolvedValue({ profile });

        const auth = useAuthStore();
        await auth.restoreSession();
        await auth.loadProfile();

        expect(auth.profile?.display_name).toBe('Kinevo');
    });

    it('updateProfile updates the profile', async () => {
        vi.mocked(authApi.updateProfile).mockResolvedValue({ profile: { ...profile, timezone: 'Asia/Jakarta' } });

        const auth = useAuthStore();
        await auth.updateProfile({ timezone: 'Asia/Jakarta' });

        expect(auth.profile?.timezone).toBe('Asia/Jakarta');
    });
});
