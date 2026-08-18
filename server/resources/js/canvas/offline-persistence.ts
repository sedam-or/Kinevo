/**
 * Offline-aware canvas persistence (TASK-044 integration).
 *
 * Wraps a normal HTTP `CanvasPersistence` and a `CanvasOfflineQueue`: when a
 * save fails because the client is offline, the mutation is queued (with a
 * local snapshot) so the edit survives tab close and is replayed on reconnect
 * (FR-57). Canvas is versioned rich content, so queued mutations are replayed
 * with their original base version and surface a conflict on staleness —
 * never a silent last-write-wins overwrite (SRS §9.4).
 */
import type { CanvasPersistence, CanvasSaveResponse } from './autosave';
import type { CanvasOfflineQueue } from './offline-queue';
import type { CanvasScene } from './types';

export class OfflineAwarePersistence implements CanvasPersistence {
    private readonly online: CanvasPersistence;
    private readonly queue: CanvasOfflineQueue;

    constructor(online: CanvasPersistence, queue: CanvasOfflineQueue) {
        this.online = online;
        this.queue = queue;
    }

    async save(canvasId: number, baseVersion: number, scene: CanvasScene): Promise<CanvasSaveResponse> {
        try {
            return await this.online.save(canvasId, baseVersion, scene);
        } catch (error) {
            const code = (error as { code?: string })?.code;
            if (code === 'OFFLINE') {
                await this.queue.enqueue(canvasId, baseVersion, scene);
                // The edit is durably queued; treat it as saved locally.
                return { version: baseVersion };
            }
            throw error;
        }
    }
}
