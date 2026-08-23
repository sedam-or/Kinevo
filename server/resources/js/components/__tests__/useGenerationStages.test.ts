import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useGenerationStages } from '../useGenerationStages';

describe('useGenerationStages (TASK-P17-011)', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => {
        vi.useRealTimers();
        vi.clearAllTimers();
    });

    it('advances Preparing context → Generating → Validating while running', () => {
        const { running, label, start, stop } = useGenerationStages();
        start();
        expect(running.value).toBe(true);
        expect(label.value).toBe('Preparing context…');

        vi.advanceTimersByTime(800);
        expect(label.value).toBe('Generating…');

        vi.advanceTimersByTime(800);
        expect(label.value).toBe('Validating…');

        // Stays on the last stage — never fakes completion.
        vi.advanceTimersByTime(2400);
        expect(label.value).toBe('Validating…');
        stop();
        expect(running.value).toBe(false);
    });

    it('stops cycling after stop()', () => {
        const { label, start, stop } = useGenerationStages();
        start();
        vi.advanceTimersByTime(800);
        stop();
        const frozen = label.value;
        vi.advanceTimersByTime(1600);
        expect(label.value).toBe(frozen);
    });
});
