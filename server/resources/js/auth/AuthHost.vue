<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuthStore } from './store';
import { useShellStore } from '../shell/store';
import { useApiStore } from '../api/store';
import AppShell from '../shell/AppShell.vue';
import AppErrorBoundary from '../shell/AppErrorBoundary.vue';
import LoginView from './LoginView.vue';
import RegisterView from './RegisterView.vue';
import ProfileView from './ProfileView.vue';

const auth = useAuthStore();
const shell = useShellStore();
const api = useApiStore();

const authMode = ref<'login' | 'register'>('login');

const ready = ref(false);

function handleOnline(): void {
    api.setOnline(true);
    shell.setSyncState('online');
}

function handleOffline(): void {
    api.setOnline(false);
    shell.setSyncState('offline');
}

onMounted(async () => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        handleOffline();
    }
    await auth.restoreSession();
    ready.value = true;
});

onBeforeUnmount(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
});

function goToRegister(): void {
    authMode.value = 'register';
}

function goToLogin(): void {
    authMode.value = 'login';
}

const viewTitle = computed(() => {
    const item = shell.navItems.find((i) => i.key === shell.activeView);
    return item ? item.label : 'Kinevo';
});
</script>

<template>
    <AppErrorBoundary>
        <!-- Session restore in progress -->
        <div v-if="!ready" class="min-h-screen flex items-center justify-center text-sm text-gray-500 dark:text-gray-400" data-testid="auth-restoring">
            Restoring session…
        </div>

        <!-- Guest: login / register -->
        <div v-else-if="!auth.isAuthenticated" class="min-h-screen flex flex-col items-center justify-center bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] px-4" data-testid="auth-gate">
            <LoginView v-if="authMode === 'login'" :on-go-to-register="goToRegister" />
            <RegisterView v-else :on-go-to-login="goToLogin" />
        </div>

        <!-- Authenticated: app shell -->
        <AppShell v-else>
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold" data-testid="view-title">{{ viewTitle }}</h1>
                <div class="flex items-center gap-3 text-sm">
                    <span data-testid="auth-user">{{ auth.displayName }}</span>
                    <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="logout" @click="auth.logout()">
                        Log out
                    </button>
                </div>
            </div>

            <div v-if="shell.isLoading" class="text-sm text-gray-500 dark:text-gray-400" data-testid="global-loading">
                Loading…
            </div>

            <ProfileView v-if="shell.activeView === 'settings'" />
            <div v-else data-testid="view-content">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    The {{ shell.activeView }} view is wired into the shell and will be
                    implemented in its dedicated task.
                </p>
            </div>
        </AppShell>
    </AppErrorBoundary>
</template>
