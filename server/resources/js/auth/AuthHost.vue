<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuthStore } from './store';
import { useShellStore } from '../shell/store';
import { useApiStore } from '../api/store';
import { MutationQueue } from '../offline/queue';
import { IndexedDbQueueStore } from '../offline/queue-store';
import { HttpMutationApplier } from '../offline/http-applier';
import { SyncStatusController } from '../offline/sync-status';
import AppShell from '../shell/AppShell.vue';
import AppErrorBoundary from '../shell/AppErrorBoundary.vue';
import LoginView from './LoginView.vue';
import RegisterView from './RegisterView.vue';
import ProfileView from './ProfileView.vue';
import TodayView from '../today/TodayView.vue';
import WeekView from '../week/WeekView.vue';
import CalendarView from '../week/CalendarView.vue';
import TaskView from '../task/TaskView.vue';
import GoalView from '../goal/GoalView.vue';
import ScheduleView from '../schedulerdraft/ScheduleView.vue';
import NoteView from '../note/NoteView.vue';
import CanvasView from '../canvas/CanvasView.vue';
// Lazy: Analytics is a secondary route; keeping it out of the initial shell
// trims the first-paint payload (§89).
const AnalyticsView = defineAsyncComponent(() => import('../analytics/AnalyticsView.vue'));
import QuickCapture from '../quickcapture/QuickCapture.vue';
import { useQuickCaptureStore } from '../quickcapture/store';
import { useKeyboardShortcuts } from '../shell/keyboard';

const auth = useAuthStore();
const shell = useShellStore();
const api = useApiStore();
const qc = useQuickCaptureStore();

const authMode = ref<'login' | 'register'>('login');

const ready = ref(false);

let syncController: SyncStatusController | null = null;

const todayDate = computed(() => {
    const local = new Date();
    const y = local.getFullYear();
    const m = String(local.getMonth() + 1).padStart(2, '0');
    const d = String(local.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
});

function isOnline(): boolean {
    return typeof navigator === 'undefined' || navigator.onLine !== false;
}

function handleOnline(): void {
    api.setOnline(true);
    shell.setSyncState('online');
    if (syncController !== null) {
        syncController.refresh();
        void syncController.sync();
    }
}

function handleOffline(): void {
    api.setOnline(false);
    shell.setSyncState('offline');
    if (syncController !== null) {
        syncController.refresh();
    }
}

function bootSyncController(): void {
    // Guard: IndexedDB is not available in test/SSR environments.
    if (typeof indexedDB === 'undefined') {
        return;
    }
    const queue = new MutationQueue(new IndexedDbQueueStore(), new HttpMutationApplier());
    syncController = new SyncStatusController(queue, (status) => {
        shell.setSyncState(status.state);
        shell.setSyncQueuedCount(status.queuedCount);
        shell.setSyncError(status.error ?? null);
    }, isOnline);
    shell.registerRetrySync(() => {
        void syncController?.retry();
    });
    syncController.refresh();
}

function shutdownSyncController(): void {
    if (syncController !== null) {
        syncController.dispose();
        syncController = null;
    }
    shell.registerRetrySync(null);
}

onMounted(async () => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        handleOffline();
    }
    bootSyncController();
    await auth.restoreSession();
    ready.value = true;
});

onBeforeUnmount(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
    shutdownSyncController();
});

function goToRegister(): void {
    authMode.value = 'register';
}

function goToLogin(): void {
    authMode.value = 'login';
}

// Global keyboard shortcuts (design.md §46). Only active once authenticated so
// the guest login screen keeps its own input handling.
useKeyboardShortcuts({
    onNavigate(view) {
        if (!auth.isAuthenticated) {
            return;
        }
        shell.setView(view);
    },
    onQuickCapture() {
        if (!auth.isAuthenticated) {
            return;
        }
        qc.show();
    },
});

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
                    <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="global-quick-capture" @click="qc.show()">
                        Quick Capture
                    </button>
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
            <TodayView v-else-if="shell.activeView === 'today'" :date="todayDate" />
            <WeekView v-else-if="shell.activeView === 'week'" :anchor-date="todayDate" />
            <CalendarView v-else-if="shell.activeView === 'calendar'" :anchor-date="todayDate" />
            <TaskView v-else-if="shell.activeView === 'tasks'" />
            <GoalView v-else-if="shell.activeView === 'goals'" />
            <ScheduleView v-else-if="shell.activeView === 'schedule'" />
            <NoteView v-else-if="shell.activeView === 'knowledge'" />
            <CanvasView v-else-if="shell.activeView === 'canvas'" />
            <AnalyticsView v-else-if="shell.activeView === 'analytics'" />
            <div v-else data-testid="view-content">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    The {{ shell.activeView }} view is wired into the shell and will be
                    implemented in its dedicated task.
                </p>
            </div>
        </AppShell>

        <!-- Global Quick Capture (authenticated only) -->
        <QuickCapture v-if="auth.isAuthenticated" />
    </AppErrorBoundary>
</template>
