<script setup lang="ts">
import { computed } from 'vue';
import { useShellStore } from './store';
import { isShellView, NAV_GROUPS, type ShellView } from './navigation';
import SyncStatusPanel from './SyncStatusPanel.vue';
import DiagnosticsPanel from '../diagnostics/DiagnosticsPanel.vue';
import NotificationCenter from '../notifications/NotificationCenter.vue';

const shell = useShellStore();

const resolvedActive = computed<ShellView>(() =>
    isShellView(shell.activeView) ? shell.activeView : 'today',
);

const currentSection = computed(() => {
    const item = NAV_GROUPS.flatMap((g) => g.items).find((i) => i.key === resolvedActive.value);
    return item ? item.label : 'Kinevo';
});

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
        <!-- Skip link (WCAG 2.2 2.4.1 Bypass Blocks): first focusable element. -->
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[var(--z-critical-overlay)] focus:bg-[var(--color-bg)] focus:text-[var(--color-text)] focus:px-3 focus:py-2 focus:rounded-sm"
            data-testid="skip-link"
        >
            Skip to content
        </a>

        <!-- Sync + notification topbar -->
        <header class="flex items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-700 px-4 py-3">
            <div class="flex items-center gap-3 min-w-0">
                <span class="font-semibold shrink-0">Kinevo</span>
                <span data-testid="current-section" class="text-sm text-gray-500 dark:text-gray-400 truncate">
                    / {{ currentSection }}
                </span>
                <span data-testid="sync-state" class="shrink-0">
                    <SyncStatusPanel />
                </span>
                <span class="shrink-0"><NotificationCenter /></span>
            </div>
            <button
                type="button"
                class="shrink-0 text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1"
                @click="cycleTheme"
                data-testid="theme-toggle"
            >
                <span class="hidden sm:inline">Theme: </span>{{ shell.theme }}
            </button>
        </header>

        <!-- Error boundary / banner -->
        <div
            v-if="shell.errorMessage"
            class="bg-[#fff2f2] dark:bg-[#1D0002] text-danger px-4 py-2 text-sm"
            role="alert"
            data-testid="error-banner"
        >
            {{ shell.errorMessage }}
            <button type="button" class="ml-2 underline" @click="shell.setError(null)">Dismiss</button>
        </div>

        <div class="flex flex-col lg:flex-row">
            <!-- Desktop side navigation -->
            <nav
                class="hidden lg:flex flex-col w-56 border-r border-gray-200 dark:border-gray-700 p-4 gap-4"
                aria-label="Primary"
                data-testid="desktop-nav"
            >
                <div v-for="group in shell.navGroups" :key="group.key" class="flex flex-col gap-1">
                    <span class="px-3 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400" data-testid="nav-group-label">
                        {{ group.label }}
                    </span>
                    <a
                        v-for="item in group.items"
                        :key="item.key"
                        href="#"
                        class="flex items-center gap-2 rounded-sm px-3 py-2 text-sm"
                        :data-testid="`nav-${item.key}`"
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
                </div>
            </nav>

            <!-- Content surface -->
            <main id="main-content" tabindex="-1" class="flex-1 p-4" data-testid="content-surface">
                <slot />
            </main>
        </div>

        <!-- Mobile bottom navigation -->
        <nav
            class="lg:hidden fixed bottom-0 inset-x-0 border-t border-gray-200 dark:border-gray-700 bg-[#FDFDFC] dark:bg-[#0a0a0a] flex justify-around"
            aria-label="Primary mobile"
            data-testid="mobile-nav"
        >
            <a
                v-for="item in shell.navItems"
                :key="item.key"
                href="#"
                class="flex-1 min-w-0 px-1 py-3 text-center text-xs truncate"
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

        <!-- Dev-only runtime diagnostics (TASK-R2; dropped in production builds) -->
        <DiagnosticsPanel />
    </div>
</template>
