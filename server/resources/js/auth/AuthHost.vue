<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuthStore } from './store';
import { useShellStore } from '../shell/store';
import { useApiStore } from '../api/store';
import { MutationQueue } from '../offline/queue';
import { IndexedDbQueueStore } from '../offline/queue-store';
import { ReconcileMutationApplier } from '../offline/reconcile-applier';
import { SyncStatusController } from '../offline/sync-status';
import { setOfflineQueue } from '../offline/queue-access';
import { useTaskStore } from '../task/store';
import { useNoteStore } from '../note/store';
import { useTodayStore } from '../today/store';
import AppShell from '../shell/AppShell.vue';
import AppErrorBoundary from '../shell/AppErrorBoundary.vue';
import LoginView from './LoginView.vue';
import RegisterView from './RegisterView.vue';
import AuthScreen from './AuthScreen.vue';
import KButton from '../components/KButton.vue';
import ProfileView from './ProfileView.vue';
import AiSettingsView from '../ai/AiSettingsView.vue';
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
import WorkspaceHome from '../workspace/WorkspaceHome.vue';
import PlanSettingsView from '../saas/PlanSettingsView.vue';
import CommandPalette from '../commands/CommandPalette.vue';
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
        const hadQueued = syncController.getQueuedCount() > 0;
        void syncController.sync().then(() => {
            if (hadQueued) {
                rehydrateAffectedStores();
            }
        });
    }
}

/**
 * ADR-017 §2.17 — bounded canonical rehydration after a drain. Reloads the
 * stores the allowlist touches instead of trusting stale optimistic values or
 * forcing a full page reload.
 */
function rehydrateAffectedStores(): void {
    const tasks = useTaskStore();
    const notes = useNoteStore();
    const today = useTodayStore();
    void tasks.loadList();
    void notes.loadList();
    if (today.date !== null) {
        void today.load(today.date);
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
    const queue = new MutationQueue(new IndexedDbQueueStore(), new ReconcileMutationApplier());
    setOfflineQueue(queue);
    syncController = new SyncStatusController(queue, (status) => {
        shell.setSyncState(status.state);
        shell.setSyncQueuedCount(status.queuedCount);
        shell.setSyncError(status.error ?? null);
    }, isOnline);
    shell.registerRetrySync(() => {
        void syncController?.retry();
    });
    shell.registerDiscardConflicts(async () => {
        await queue.discardConflicts();
        syncController?.refresh();
    });
    syncController.refresh();
}

function shutdownSyncController(): void {
    if (syncController !== null) {
        syncController.dispose();
        syncController = null;
    }
    setOfflineQueue(null);
    shell.registerRetrySync(null);
    shell.registerDiscardConflicts(null);
}

onMounted(async () => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        handleOffline();
    }
    bootSyncController();
    await auth.restoreSession();
    if (auth.isAuthenticated && syncController !== null) {
        // ADR-017 §2.15/§2.16 — reload-with-queued: drain what survived the
        // reload once online, then rehydrate the affected stores.
        const hadQueued = syncController.getQueuedCount() > 0;
        if (hadQueued) {
            await syncController.sync();
            rehydrateAffectedStores();
        }
    }
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
const paletteOpen = ref(false);

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
    onCommandPalette() {
        if (!auth.isAuthenticated) {
            return;
        }
        paletteOpen.value = true;
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
        <div v-if="!ready" class="flex min-h-[100dvh] items-center justify-center bg-bg text-sm text-text-muted" data-testid="auth-restoring">
            Restoring session…
        </div>

        <!-- Guest: login / register -->
        <AuthScreen v-else-if="!auth.isAuthenticated">
            <LoginView v-if="authMode === 'login'" :on-go-to-register="goToRegister" />
            <RegisterView v-else :on-go-to-login="goToLogin" />
        </AuthScreen>

        <!-- Authenticated: app shell -->
        <AppShell v-else>
            <div class="mb-5 flex items-center justify-between gap-3">
                <h1 class="flex items-center gap-2.5 text-xl font-bold tracking-tight" data-testid="view-title">
                    <span class="size-2.5 bg-primary" aria-hidden="true"></span>
                    {{ viewTitle }}
                </h1>
                <div class="flex items-center gap-3 text-sm">
                    <KButton variant="primary" data-testid="global-quick-capture" @click="qc.show()">
                        Quick Capture
                    </KButton>
                    <span class="hidden text-text-muted sm:inline" data-testid="auth-user">{{ auth.displayName }}</span>
                    <KButton variant="ghost" data-testid="logout" @click="auth.logout()">
                        Log out
                    </KButton>
                </div>
            </div>

            <div v-if="shell.isLoading" class="text-sm text-text-muted" data-testid="global-loading">
                Loading…
            </div>

            <ProfileView v-if="shell.activeView === 'settings'" />
            <PlanSettingsView v-else-if="shell.activeView === 'plan-settings'" />
            <AiSettingsView v-else-if="shell.activeView === 'ai-settings'" />
            <WorkspaceHome v-else-if="shell.activeView === 'workspace-home'" />
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
                <p class="text-sm text-text-muted">
                    The {{ shell.activeView }} view is wired into the shell and will be
                    implemented in its dedicated task.
                </p>
            </div>
        </AppShell>

        <!-- Global Quick Capture (authenticated only) -->
        <!-- Mounted only while open: useFocusTrap's onMounted must fire when
             the dialog appears, or initial focus never lands (TASK-R6). -->
        <QuickCapture v-if="auth.isAuthenticated && qc.open" />
        <CommandPalette v-if="auth.isAuthenticated" v-model:open="paletteOpen" />
    </AppErrorBoundary>
</template>
