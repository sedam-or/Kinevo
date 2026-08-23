import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { NAV_GROUPS, NAV_ITEMS, MOBILE_MORE_KEYS, MOBILE_PRIMARY_KEYS, PRIMARY_VIEW, type ShellView } from './navigation';
import { applyTheme, readThemePreference, writeThemePreference, type ThemePreference } from './theme';
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
    // TASK-P17-013: in system mode the theme follows live OS switches, not
    // just the value captured at startup. The store lives for the app's
    // lifetime, so no teardown is needed.
    if (typeof window !== 'undefined' && window.matchMedia) {
        window
            .matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', () => {
                if (theme.value === 'system') {
                    applyTheme('system');
                }
            });
    }

    const navItems = computed(() => NAV_ITEMS);
    const navGroups = computed(() => NAV_GROUPS);
    const unreadCount = computed(() => notifications.value.filter((n) => n.unread).length);
    /** Primary mobile bottom-nav items (design.md §8.3). */
    const mobilePrimaryItems = computed(() => NAV_ITEMS.filter((i) => MOBILE_PRIMARY_KEYS.includes(i.key)));
    /** Everything that lives behind the mobile "More" drawer. */
    const mobileMoreItems = computed(() => NAV_ITEMS.filter((i) => MOBILE_MORE_KEYS.includes(i.key)));

    /**
     * Pending deep-open target per view (TASK-P17-002 workflow continuity).
     * A related-entity link navigates to a surface AND records which object
     * should be opened there; the surface consumes (and clears) it on mount.
     */
    const viewFocus = ref<Partial<Record<ShellView, number>>>({});

    function setView(view: ShellView, focusId?: number): void {
        activeView.value = view;
        if (focusId !== undefined) {
            viewFocus.value[view] = focusId;
        }
    }

    function consumeFocus(view: ShellView): number | null {
        const id = viewFocus.value[view] ?? null;
        if (id !== null) {
            delete viewFocus.value[view];
        }
        return id;
    }

    function setTheme(preference: ThemePreference): void {
        theme.value = preference;
        // TASK-P17-013: persist, not just apply — the toggle was losing the
        // preference on reload.
        writeThemePreference(preference);
    }
    function cycleTheme(): void {
        const order: ThemePreference[] = ['light', 'dark', 'system'];
        setTheme(order[(order.indexOf(theme.value) + 1) % order.length]);
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
        cycleTheme,
        isLoading,
        syncState,
        syncQueuedCount,
        syncError,
        retrySync,
        notifications,
        errorMessage,
        navItems,
        navGroups,
        mobilePrimaryItems,
        mobileMoreItems,
        viewFocus,
        unreadCount,
        setView,
        consumeFocus,
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
