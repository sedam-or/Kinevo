<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useNotificationsStore } from './store';
import type { KinevoNotification } from './types';
import KButton from '../components/KButton.vue';

/**
 * Notification center (§28-§29): bell + toast -> center -> contextual prompt.
 * The center groups Unread / Today / Earlier as design.md §29 requires.
 */
const store = useNotificationsStore();

const open = ref(false);

onMounted(() => {
    void store.load();
});

function explanationFor(n: KinevoNotification): string {
    if (n.title) {
        return n.title;
    }
    if (n.type === 'reconciliation') {
        return 'Review the schedule reconciliation to keep your plan safe.';
    }
    if (n.type === 'break_end') {
        return 'Your break period has ended; scheduling resumes.';
    }
    return 'New update from Kinevo.';
}

function iconFor(n: KinevoNotification): string {
    if (n.type === 'reconciliation') {
        return '⚠';
    }
    return '↻';
}

function timestampFor(n: KinevoNotification): string {
    return n.scheduled_for ? `scheduled ${n.scheduled_for}` : 'now';
}

function toggle(): void {
    open.value = !open.value;
    if (open.value && store.notifications.length === 0) {
        void store.load();
    }
}

async function onRead(n: KinevoNotification): Promise<void> {
    await store.markRead(n.id);
}
</script>

<template>
    <div class="relative" data-testid="notifications">
        <button
            type="button"
            class="flex items-center gap-1 text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="Notifications"
            data-testid="notifications-bell"
            @click="toggle"
        >
            <span aria-hidden="true">🔔</span>
            <span v-if="store.unreadCount > 0" class="text-xs font-semibold" data-testid="notifications-count">
                {{ store.unreadCount }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 top-full z-[var(--z-popover)] mt-1 w-80 max-w-[90vw] border border-gray-300 dark:border-gray-600 rounded-sm bg-[var(--color-bg)] text-[var(--color-text)] shadow-lg p-3"
            data-testid="notification-center"
        >
            <div class="flex items-center justify-between mb-2">
                <strong class="uppercase tracking-wide text-xs opacity-70">Notifications</strong>
                <button type="button" class="text-xs underline opacity-70 hover:opacity-100" data-testid="notifications-close" @click="open = false">close</button>
            </div>

            <div v-if="store.error" class="text-sm text-[var(--color-danger)]" role="alert" data-testid="notifications-error">{{ store.error }}</div>
            <div v-if="store.loading && store.notifications.length === 0" class="text-sm text-gray-500" data-testid="notifications-loading">Loading…</div>

            <template v-if="!store.loading || store.notifications.length > 0">
                <section v-for="group in (['unread', 'today', 'earlier'] as const)" :key="group" class="mb-3">
                    <div v-if="store.grouped[group].length > 0" class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">{{ group }}</div>
                    <ul v-if="store.grouped[group].length > 0" class="space-y-1">
                        <li
                            v-for="n in store.grouped[group]"
                            :key="n.id"
                            class="flex items-start gap-2 border border-gray-200 dark:border-gray-700 rounded-sm px-2 py-2"
                            data-testid="notification-item"
                        >
                            <span class="text-sm" aria-hidden="true">{{ iconFor(n) }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm" data-testid="notification-title">{{ explanationFor(n) }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ timestampFor(n) }}</div>
                            </div>
                            <KButton v-if="n.read_at === null" variant="ghost" class="text-xs" data-testid="notification-read" @click="onRead(n)">
                                Mark read
                            </KButton>
                        </li>
                    </ul>
                </section>

                <div v-if="store.notifications.length === 0 && !store.loading" class="text-sm text-gray-500 dark:text-gray-400" data-testid="notifications-empty">
                    You're all caught up.
                </div>
            </template>
        </div>
    </div>
</template>