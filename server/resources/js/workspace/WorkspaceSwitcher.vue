<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { useWorkspaceStore } from './store';

/**
 * The single reusable workspace switcher (TASK-P19-005).
 * - current workspace is unmistakable (label + check glyph);
 * - keyboard accessible (button + arrow/enter navigation, Escape closes);
 * - mobile friendly (same popover, touch-sized rows);
 * - archived workspaces are excluded (server contract, P19-030);
 * - selection persists through the store (reload/deep-link safe, P19-006).
 */
const store = useWorkspaceStore();
const open = ref(false);
const root = ref<HTMLElement | null>(null);

const options = computed(() => store.workspaces);
const current = computed(() => store.activeWorkspace);

// A context switch must leave every surface consistent — the app
// rehydrates all scoped lists deterministically through a full reload.
function reloadIntoContext(): void {
    window.location.reload();
}

async function choose(id: number): Promise<void> {
    if (store.switchTo(id)) {
        open.value = false;
        reloadIntoContext();
    }
}

function onDocumentClick(event: MouseEvent): void {
    if (root.value !== null && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    void store.load();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative" data-testid="workspace-switcher">
        <button
            type="button"
            class="flex items-center gap-2 rounded-sm border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-sm min-h-[44px]"
            :aria-expanded="open"
            aria-haspopup="listbox"
            data-testid="workspace-switcher-trigger"
            @click.stop="open = !open"
        >
            <span
                v-if="current?.accent"
                class="inline-block h-2.5 w-2.5 rounded-full"
                :style="{ backgroundColor: current.accent }"
                aria-hidden="true"
            />
            <span class="font-medium" data-testid="workspace-current-name">{{ current?.name ?? 'Workspace' }}</span>
            <span v-if="current?.is_default" class="text-xs text-gray-500 dark:text-gray-400" data-testid="workspace-default-badge">default</span>
            <span aria-hidden="true" class="text-xs">▾</span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-[var(--z-dropdown)] mt-1 w-64 rounded-sm border border-gray-200 dark:border-gray-700 bg-surface shadow-rest p-2 flex flex-col gap-1"
            role="listbox"
            aria-label="Workspaces"
            data-testid="workspace-switcher-menu"
        >
            <!-- TASK-P19-028 — explicit All-Workspaces (global) view. -->
            <button
                type="button"
                role="option"
                :aria-selected="store.activeWorkspaceId === null"
                class="flex items-center justify-between gap-2 rounded-sm px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800 min-h-[44px] border-b border-gray-200 dark:border-gray-700 mb-1"
                data-testid="workspace-option-all"
                @click="store.switchToGlobal(); open = false; reloadIntoContext()"
            >
                <span class="font-medium">All workspaces</span>
                <span v-if="store.activeWorkspaceId === null" aria-hidden="true">✓</span>
            </button>

            <button
                v-for="w in options"
                :key="w.id"
                type="button"
                role="option"
                :aria-selected="w.id === current?.id"
                class="flex items-center justify-between gap-2 rounded-sm px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800 min-h-[44px]"
                :data-testid="`workspace-option-${w.slug}`"
                @click="choose(w.id)"
            >
                <span class="flex items-center gap-2">
                    <span
                        v-if="w.accent"
                        class="inline-block h-2.5 w-2.5 rounded-full"
                        :style="{ backgroundColor: w.accent }"
                        aria-hidden="true"
                    />
                    <span>{{ w.name }}</span>
                    <span v-if="w.is_default" class="text-xs text-gray-500 dark:text-gray-400">(default)</span>
                </span>
                <span v-if="w.id === current?.id" aria-hidden="true" data-testid="workspace-active-check">✓</span>
            </button>

            <slot name="footer" />
        </div>
    </div>
</template>
