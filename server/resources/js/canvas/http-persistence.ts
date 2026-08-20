/**
 * HTTP canvas persistence — implements the framework-agnostic
 * `CanvasPersistence` contract against the Kinevo canvas API.
 *
 * It maps transport failures to the domain codes the autosave controller
 * understands:
 * - stale base version (409) -> `CANVAS_VERSION_CONFLICT` (FR-56);
 * - offline / network failure  -> `OFFLINE` (FR-57);
 * - anything else propagates so the controller marks the save as failed.
 */
import { ApiErrorClass } from '../api/client';
import { canvasApi } from './api';
import type { CanvasPersistence, CanvasSaveResponse } from './autosave';
import type { CanvasScene } from './types';

export class HttpCanvasPersistence implements CanvasPersistence {
    async save(canvasId: number, baseVersion: number, scene: CanvasScene): Promise<CanvasSaveResponse> {
        try {
            const { document } = await canvasApi.save(canvasId, {
                scene_json: scene as unknown as Record<string, unknown>,
                base_version: baseVersion,
            });
            return { version: document.version };
        } catch (error) {
            if (error instanceof ApiErrorClass) {
                if (error.status === 409) {
                    throw Object.assign(new Error('Canvas was changed elsewhere.'), {
                        code: 'CANVAS_VERSION_CONFLICT',
                    });
                }
                if (error.code === 'OFFLINE' || error.code === 'NETWORK') {
                    throw Object.assign(new Error('You are offline.'), { code: 'OFFLINE' });
                }
            }
            throw error;
        }
    }
}