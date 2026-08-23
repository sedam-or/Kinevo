import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { useSaveState } from '../useSaveState';

describe('useSaveState (TASK-P17-011)', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('moves idle → saving → saved → idle around a successful save', async () => {
        const { state, label, run } = useSaveState();
        expect(state.value).toBe('idle');
        expect(label('Log')).toBe('Log');

        const promise = run(async () => 'ok');
        expect(state.value).toBe('saving');
        expect(label('Log')).toBe('Saving…');

        await promise;
        expect(state.value).toBe('saved');
        expect(label('Log')).toBe('Saved ✓');

        vi.advanceTimersByTime(1500);
        expect(state.value).toBe('idle');
    });

    it('treats a null outcome as a failed save (no false Saved ✓)', async () => {
        const { state, run } = useSaveState();
        const ok = await run(async () => null);
        expect(ok).toBe(false);
        expect(state.value).toBe('idle');
    });

    it('returns to idle when the save throws', async () => {
        const { state, run } = useSaveState();
        await expect(run(async () => {
            throw new Error('boom');
        })).rejects.toThrow('boom');
        expect(state.value).toBe('idle');
    });
});
