<script setup lang="ts">
import { computed } from 'vue';
import { useShellStore, type SyncState } from './store';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import { SYNC_STATE_EXPLANATIONS } from '../offline/sync-status';

const shell = useShellStore();

const state = computed<SyncState>(() => shell.syncState);
const queuedCount = computed(() => shell.syncQueuedCount);
const error = computed(() => shell.syncError);

const explanation = computed(() => SYNC_STATE_EXPLANATIONS[state.value] ?? '');

const showRetry = computed(() => state.value === 'retrying' || state.value === 'failed');
const showDiscard = computed(() => state.value === 'conflict');

function retry(): void {
    shell.retrySync?.();
}

function discard(): void {
    shell.discardConflicts?.();
}
</script>

<template>
    <!-- role=status + aria-live: screen readers announce offline/retrying/failed
         transitions politely instead of leaving them visual-only (§5.2, §34.6). -->
    <div class="flex items-center gap-2" data-testid="sync-status-panel" role="status" aria-live="polite">
        <span data-testid="sync-status-badge">
            <VisualStateBadge :state="state" />
        </span>
        <span v-if="queuedCount > 0" class="text-xs text-text-muted" data-testid="sync-queued-count">
            {{ queuedCount }} queued
        </span>
        <!-- Clamped on small screens (CSS truncation keeps the full text in
             the a11y tree); full prose from lg up. §90 state explanations. -->
        <span
            class="text-xs text-text-muted max-w-24 lg:max-w-none truncate"
            data-testid="sync-explanation"
        >
            {{ explanation }}
        </span>
        <button
            v-if="showRetry"
            type="button"
            class="text-xs border border-border/30 rounded-sm px-2 py-0.5"
            data-testid="sync-retry"
            @click="retry"
        >
            Retry sync
        </button>
        <span v-if="error && showRetry" class="text-xs text-danger" data-testid="sync-error">
            {{ error }}
        </span>
        <button
            v-if="showDiscard"
            type="button"
            class="text-xs border border-border/30 rounded-sm px-2 py-0.5"
            data-testid="sync-discard-conflicts"
            @click="discard"
        >
            Discard local change
        </button>
    </div>
</template>