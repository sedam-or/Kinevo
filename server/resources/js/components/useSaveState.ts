/**
 * Save-state feedback (TASK-P17-011, design.md §104: Saving… → Saved ✓).
 * Wraps an async save and exposes the button label + whether the saved
 * confirmation is showing. Saved state clears itself after a moment.
 */
import { ref } from 'vue';

export type SaveState = 'idle' | 'saving' | 'saved';

export function useSaveState(restoreAfterMs = 1500) {
    const state = ref<SaveState>('idle');
    let timer: ReturnType<typeof setTimeout> | undefined;

    function label(idle: string): string {
        if (state.value === 'saving') {
            return 'Saving…';
        }
        if (state.value === 'saved') {
            return 'Saved ✓';
        }
        return idle;
    }

    async function run(save: () => Promise<unknown>): Promise<boolean> {
        state.value = 'saving';
        let outcome: unknown;
        try {
            outcome = await save();
        } catch (e) {
            state.value = 'idle';
            throw e;
        }
        // A store action that reports failure by returning null/false is NOT
        // a successful save.
        if (outcome === null || outcome === false) {
            state.value = 'idle';
            return false;
        }
        state.value = 'saved';
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => {
            state.value = 'idle';
        }, restoreAfterMs);
        return true;
    }

    return { state, label, run };
}
