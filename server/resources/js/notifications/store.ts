import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { notificationApi } from './api';
import { groupNotification, type KinevoNotification, type KinevoNotificationGroup } from './types';

/** Tracks the in-app notification center (§28-§29) backed by /notifications. */
export const useNotificationsStore = defineStore('notifications', () => {
    const notifications = ref<KinevoNotification[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const unreadCount = computed(() => notifications.value.filter((n) => n.read_at === null).length);

    const grouped = computed<Record<KinevoNotificationGroup, KinevoNotification[]>>(() => ({
        unread: notifications.value.filter((n) => groupNotification(n) === 'unread'),
        today: notifications.value.filter((n) => groupNotification(n) === 'today'),
        earlier: notifications.value.filter((n) => groupNotification(n) === 'earlier'),
    }));

    async function load(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const data = await notificationApi.list();
            notifications.value = data.notifications.map((n) => ({ ...n }));
        } catch (err) {
            error.value = (err as { message?: string }).message ?? 'Could not load notifications.';
        } finally {
            loading.value = false;
        }
    }

    async function markRead(notificationId: number): Promise<void> {
        try {
            await notificationApi.markRead(notificationId);
            const item = notifications.value.find((n) => n.id === notificationId);
            if (item) {
                item.read_at = new Date().toISOString();
            }
        } catch (err) {
            error.value = (err as { message?: string }).message ?? 'Could not mark read.';
        }
    }

    function clear(): void {
        notifications.value = [];
        error.value = null;
    }

    return {
        notifications,
        loading,
        error,
        unreadCount,
        grouped,
        load,
        markRead,
        clear,
    };
});