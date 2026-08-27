<script setup lang="ts">
import { useShellStore } from '../shell/store';
import type { ShellView } from '../shell/navigation';

/**
 * Workflow continuity strip (TASK-P17-002, design.md §104 P17-B).
 *
 * Renders the contextual upstream/downstream links for an entity page so no
 * surface is a dead end. Each chip navigates to its shell view and optionally
 * records a deep-open target (consumed by that view on mount).
 */
export interface EntityLink {
    label: string;
    view: ShellView;
    focusId?: number;
}

const props = defineProps<{
    links: EntityLink[];
}>();

const shell = useShellStore();

function go(link: EntityLink): void {
    if (link.focusId !== undefined) {
        shell.setView(link.view, link.focusId);
        return;
    }
    shell.setView(link.view);
}
</script>

<template>
    <nav
        v-if="props.links.length > 0"
        aria-label="Related"
        data-testid="entity-links"
        class="flex items-center gap-2 flex-wrap"
    >
        <span class="text-xs uppercase text-text-muted">Related</span>
        <button
            v-for="link in props.links"
            :key="`${link.view}-${link.focusId ?? ''}`"
            type="button"
            class="text-xs border border-border rounded-sm px-2 py-1 hover:bg-surface"
            :data-testid="`entity-link-${link.view}`"
            @click="go(link)"
        >
            {{ link.label }}
        </button>
    </nav>
</template>
