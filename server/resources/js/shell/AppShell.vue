<script setup lang="ts">
import { computed } from 'vue';
import { useShellStore } from './store';
import { isShellView, type ShellView } from './navigation';
import SyncStatusPanel from './SyncStatusPanel.vue';

const shell = useShellStore();

const resolvedActive = computed<ShellView>(() =>
    isShellView(shell.activeView) ? shell.activeView : 'today',
);

function selectView(view: ShellView): void {
    shell.setView(view);
}

function cycleTheme(): void {
    const order = ['light', 'dark', 'system'] as const;
    const next = order[(order.indexOf(shell.theme) + 1) % order.length];
    shell.setTheme(next);
}
</script>

<template>
    <div class="kinevo-app-shell min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <!-- Sync + notification topbar -->
        <header class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="font-semibold">Kinevo</span>
                <span data-testid="sync-state">
                    <SyncStatusPanel />
                </span>
                <span v-if="shell.unreadCount > 0" class="text-xs" data-testid="notifications">
                    {{ shell.unreadCount }} unread
                </span>
            </div>
            <button
                type="button"
                class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1"
                @click="cycleTheme"
                data-testid="theme-toggle"
            >
                Theme: {{ shell.theme }}
            </button>
        </header>

        <!-- Error boundary / banner -->
        <div
            v-if="shell.errorMessage"
            class="bg-[#fff2f2] dark:bg-[#1D0002] text-[#F53003] px-4 py-2 text-sm"
            role="alert"
            data-testid="error-banner"
        >
            {{ shell.errorMessage }}
            <button type="button" class="ml-2 underline" @click="shell.setError(null)">Dismiss</button>
        </div>

        <div class="flex flex-col lg:flex-row">
            <!-- Desktop side navigation -->
            <nav
                class="hidden lg:flex flex-col w-56 border-r border-gray-200 dark:border-gray-700 p-4 gap-1"
                aria-label="Primary"
                data-testid="desktop-nav"
            >
                <a
                    v-for="item in shell.navItems"
                    :key="item.key"
                    href="#"
                    class="flex items-center gap-2 rounded-sm px-3 py-2 text-sm"
                    :class="
                        resolvedActive === item.key
                            ? 'bg-gray-100 dark:bg-gray-800 font-medium'
                            : 'hover:bg-gray-100 dark:hover:bg-gray-800'
                    "
                    :aria-current="resolvedActive === item.key ? 'page' : undefined"
                    @click.prevent="selectView(item.key)"
                >
                    {{ item.label }}
                </a>
            </nav>

            <!-- Content surface -->
            <main class="flex-1 p-4" data-testid="content-surface">
                <slot />
            </main>
        </div>

        <!-- Mobile bottom navigation -->
        <nav
            class="lg:hidden fixed bottom-0 inset-x-0 border-t border-gray-200 dark:border-gray-700 bg-[#FDFDFC] dark:bg-[#0a0a0a] flex justify-around"
            aria-label="Primary"
            data-testid="mobile-nav"
        >
            <a
                v-for="item in shell.navItems"
                :key="item.key"
                href="#"
                class="flex-1 py-3 text-center text-xs"
                :class="
                    resolvedActive === item.key
                        ? 'text-[#1b1b18] dark:text-[#EDEDEC] font-medium'
                        : 'text-gray-500 dark:text-gray-400'
                "
                :aria-current="resolvedActive === item.key ? 'page' : undefined"
                @click.prevent="selectView(item.key)"
            >
                {{ item.label }}
            </a>
        </nav>
    </div>
</template>
