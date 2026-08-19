import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { authApi } from './client';
import { clearToken, readToken, writeToken } from '../api/token';
import type { Profile, UpdateProfilePayload, User } from './types';

export type AuthStatus = 'unknown' | 'authenticated' | 'guest';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const profile = ref<Profile | null>(null);
    const status = ref<AuthStatus>('unknown');
    const busy = ref(false);

    const isAuthenticated = computed(() => status.value === 'authenticated');
    const displayName = computed(() => profile.value?.display_name ?? user.value?.name ?? '');

    function setSession(nextUser: User, token: string, nextProfile?: Profile): void {
        user.value = nextUser;
        profile.value = nextProfile ?? profile.value;
        writeToken(token);
        status.value = 'authenticated';
    }

    async function restoreSession(): Promise<void> {
        const token = readToken();
        if (token === null) {
            status.value = 'guest';
            return;
        }

        try {
            const { user: nextUser } = await authApi.me();
            user.value = nextUser;
            status.value = 'authenticated';
        } catch {
            clearToken();
            user.value = null;
            profile.value = null;
            status.value = 'guest';
        }
    }

    async function login(email: string, password: string): Promise<void> {
        busy.value = true;
        try {
            const { user: nextUser, token } = await authApi.login({ email, password });
            setSession(nextUser, token);
        } finally {
            busy.value = false;
        }
    }

    async function register(name: string, email: string, password: string): Promise<void> {
        busy.value = true;
        try {
            const { user: nextUser, token, profile: nextProfile } = await authApi.register({ name, email, password });
            setSession(nextUser, token, nextProfile);
        } finally {
            busy.value = false;
        }
    }

    async function logout(): Promise<void> {
        try {
            await authApi.logout();
        } catch {
            // best-effort: clear local session regardless of server response
        } finally {
            clearToken();
            user.value = null;
            profile.value = null;
            status.value = 'guest';
        }
    }

    async function loadProfile(): Promise<void> {
        if (!isAuthenticated.value) {
            return;
        }
        const { profile: nextProfile } = await authApi.profile();
        profile.value = nextProfile;
    }

    async function updateProfile(payload: UpdateProfilePayload): Promise<void> {
        const { profile: nextProfile } = await authApi.updateProfile(payload);
        profile.value = nextProfile;
    }

    return {
        user,
        profile,
        status,
        busy,
        isAuthenticated,
        displayName,
        restoreSession,
        login,
        register,
        logout,
        loadProfile,
        updateProfile,
    };
});
