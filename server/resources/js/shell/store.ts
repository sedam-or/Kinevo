import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { NAV_GROUPS, NAV_ITEMS, PRIMARY_VIEW, type ShellView } from './navigation';
import { applyTheme, readThemePreference, type ThemePreference } from './theme';
import { VISIBLE_SYNC_STATES, type VisibleSyncState } from '../offline/sync-status';

/** The visible sync states the shell presents (TASK-115). */
export type SyncState = VisibleSyncState;

/** All visible sync states a user can be told about (TASK-115). */
export const SYNC_STATES: readonly SyncState[] = VISIBLE_SYNC_STATES;

export interface ShellNotification {
    id: number;
    title: string;
    unread: boolean;
}

export const useShellStore = defineStore('shell', () => {
    const activeView = ref<ShellView>(PRIMARY_VIEW);
    const theme = ref<ThemePreference>(readThemePreference());
    const isLoading = ref(false);
    const syncState = ref<SyncState>('online');
    const syncQueuedCount = ref(0);
    const syncError = ref<string | null>(null);
    const retrySync = ref<(() => void) | null>(null);
    const notifications = ref<ShellNotification[]>([]);
    const errorMessage = ref<string | null>(null);

    applyTheme(theme.value);

    const navItems = computed(() => NAV_ITEMS);
    const navGroups = computed(() => NAV_GROUPS);
    const unreadCount = computed(() => notifications.value.filter((n) => n.unread).length);

    function setView(view: ShellView): void {
        activeView.value = view;
    }

    function setTheme(preference: ThemePreference): void {
        theme.value = preference;
        applyTheme(preference);
    }

    function setLoading(loading: boolean): void {
        isLoading.value = loading;
    }

    function setSyncState(state: SyncState): void {
        syncState.value = state;
    }

    /** Track how many offline mutations are queued (0 when nothing pending). */
    function setSyncQueuedCount(count: number): void {
        syncQueuedCount.value = count;
    }

    /** Track the last sync failure message (null when healthy). */
    function setSyncError(message: string | null): void {
        syncError.value = message;
    }

    /** Register the controller-owned retry action used by the UI. */
    function registerRetrySync(action: (() => void) | null): void {
        retrySync.value = action;
    }

    function setNotifications(items: ShellNotification[]): void {
        notifications.value = items;
    }

    function setError(message: string | null): void {
        errorMessage.value = message;
    }

    return {
        activeView,
        theme,
        isLoading,
        syncState,
        syncQueuedCount,
        syncError,
        retrySync,
        notifications,
        errorMessage,
        navItems,
        navGroups,
        unreadCount,
        setView,
        setTheme,
        setLoading,
        setSyncState,
        setSyncQueuedCount,
        setSyncError,
        registerRetrySync,
        setNotifications,
        setError,
    };
});
