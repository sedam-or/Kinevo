import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { NAV_ITEMS, PRIMARY_VIEW, type ShellView } from './navigation';
import { applyTheme, readThemePreference, type ThemePreference } from './theme';

export type SyncState = 'online' | 'offline' | 'syncing' | 'queued' | 'saved' | 'failed';

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
    const notifications = ref<ShellNotification[]>([]);
    const errorMessage = ref<string | null>(null);

    applyTheme(theme.value);

    const navItems = computed(() => NAV_ITEMS);
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
        notifications,
        errorMessage,
        navItems,
        unreadCount,
        setView,
        setTheme,
        setLoading,
        setSyncState,
        setNotifications,
        setError,
    };
});
