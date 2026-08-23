/**
 * AI generation stage feedback (TASK-P17-011, design.md §104:
 * Preparing context → Generating → Validating → Proposal ready).
 * The backend call is one request; the stages are honest progress labels
 * advanced on a timer while it runs — never fake completion. The caller
 * decides what "done" looks like (proposal ready / error).
 */
import { ref, computed } from 'vue';

const STAGES = ['Preparing context…', 'Generating…', 'Validating…'] as const;
const STAGE_MS = 800;

export function useGenerationStages() {
    const running = ref(false);
    const stageIndex = ref(0);
    let timer: ReturnType<typeof setInterval> | undefined;

    const label = computed(() => STAGES[Math.min(stageIndex.value, STAGES.length - 1)]);

    function start(): void {
        running.value = true;
        stageIndex.value = 0;
        timer = setInterval(() => {
            if (stageIndex.value < STAGES.length - 1) {
                stageIndex.value += 1;
            }
        }, STAGE_MS);
    }

    function stop(): void {
        running.value = false;
        if (timer) {
            clearInterval(timer);
            timer = undefined;
        }
    }

    return { running, label, start, stop };
}
